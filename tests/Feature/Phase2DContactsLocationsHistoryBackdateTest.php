<?php

namespace Tests\Feature;

use App\Enums\CarePlanStatus;
use App\Enums\ClientAdminHistoryCategory;
use App\Enums\ClientContactRole;
use App\Enums\ClientStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\CarePlanEffectiveDateCorrection;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientAdminHistoryEvent;
use App\Models\ClientContact;
use App\Models\ClientDspAssignment;
use App\Models\ClientServiceLocation;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\CarePlanRevisionService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2DContactsLocationsHistoryBackdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        Carbon::setTestNow('2026-10-16 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailablePayload(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
        ];
    }

    public function test_admin_controlled_effective_date_correction_preserves_visit_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-10',
            'effective_at' => '2026-10-10 00:00:00',
            'title' => 'Standing Plan',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Morning ADLs',
            'recurrence' => TaskRecurrence::Daily,
        ]);

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'supervisor_id' => $supervisor->id,
        ]);

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame($plan->id, $visit->care_plan_id);
        $this->assertSame('Morning ADLs', VisitTask::query()->where('visit_id', $visit->id)->value('title'));

        $this->actingAs($admin)
            ->post(route('care-plans.correct-effective-date', $plan), [
                'corrected_starts_on' => '2026-10-01',
                'reason' => 'Paper plan started October 1; MDM recorded October 10 in error.',
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('2026-10-01', $plan->starts_on->toDateString());
        $correction = CarePlanEffectiveDateCorrection::query()
            ->where('care_plan_id', $plan->id)
            ->firstOrFail();
        $this->assertSame('2026-10-10', $correction->previous_starts_on->toDateString());
        $this->assertSame('2026-10-01', $correction->corrected_starts_on->toDateString());

        $visit->refresh();
        $this->assertSame($plan->id, $visit->care_plan_id);
        $this->assertSame('Morning ADLs', VisitTask::query()->where('visit_id', $visit->id)->value('title'));
        $this->assertSame(VisitStatus::InProgress, $visit->status);

        $this->assertDatabaseHas('client_admin_history_events', [
            'client_id' => $client->id,
            'category' => ClientAdminHistoryCategory::EffectiveDateCorrection->value,
        ]);
    }

    public function test_supervisor_cannot_perform_effective_date_correction_and_routine_backdate_still_blocked(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-10',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Task',
            'recurrence' => TaskRecurrence::Daily,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('care-plans.correct-effective-date', $plan), [
                'corrected_starts_on' => '2026-10-01',
                'reason' => 'Supervisor attempted correction',
            ])
            ->assertForbidden();

        $this->expectException(ValidationException::class);

        app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-10-01',
            'revision_reason' => 'Should fail',
            'tasks' => [[
                'title' => 'Changed',
                'recurrence' => TaskRecurrence::Daily->value,
                'is_required' => true,
                'can_skip' => true,
                'note_required' => false,
                'is_critical' => false,
            ]],
        ], $supervisor->user()->firstOrFail());
    }

    public function test_contact_roles_and_dsp_visibility_rules(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create([
            'address_line_1' => '100 Main St',
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => '43215',
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
        ]);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('clients.contacts.store', $client), [
                'role' => ClientContactRole::Emergency->value,
                'name' => 'Emergency Parent',
                'relationship' => 'Parent',
                'phone' => '555-111-2222',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('clients.contacts.store', $client), [
                'role' => ClientContactRole::Guardian->value,
                'name' => 'Legal Guardian',
                'relationship' => 'Guardian',
                'phone' => '555-333-4444',
            ])
            ->assertRedirect();

        $client->refresh();
        $this->assertSame('Emergency Parent', $client->emergency_contact_name);
        $this->assertSame(2, ClientContact::query()->where('client_id', $client->id)->count());

        Carbon::setTestNow('2026-10-16 08:45:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts', fn ($contacts) => collect($contacts)->pluck('role')->all() === ['emergency'])
                ->where('admin_history', [])
                ->where('can.viewAdminHistory', false));

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contacts', 2)
                ->where('can.viewAdminHistory', true)
                ->has('admin_history'));
    }

    public function test_service_location_selection_snapshots_and_primary_change_does_not_rewrite_history(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create([
            'address_line_1' => '100 Main St',
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => '43215',
        ]);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $primary = ClientServiceLocation::factory()->forClient($client)->create([
            'label' => 'Home',
            'address_line_1' => '100 Main St',
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => '43215',
            'latitude' => 39.9612000,
            'longitude' => -82.9988000,
            'is_primary' => true,
        ]);
        $alternate = ClientServiceLocation::factory()->forClient($client)->alternate('Day program')->create([
            'address_line_1' => '200 Center Ave',
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => '43215',
            'latitude' => 40.0001000,
            'longitude' => -83.0002000,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'client_service_location_id' => $alternate->id,
                'service_date' => '2026-10-20',
                'starts_at' => '09:00:00',
                'ends_at' => '17:00:00',
                'service_type' => 'Residential Habilitation',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertRedirect();

        $visit = ScheduledVisit::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
        $this->assertSame($alternate->id, $visit->client_service_location_id);
        $this->assertSame('Day program', $visit->service_location_label);
        $this->assertSame('200 Center Ave', $visit->service_address_line_1);
        $this->assertEqualsWithDelta(40.0001, (float) $visit->service_latitude, 0.0001);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.service-locations.update', [$client, $primary]), [
                'label' => 'Home updated',
                'address_line_1' => '100 Main St',
                'city' => 'Columbus',
                'state' => 'OH',
                'postal_code' => '43215',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $visit->refresh();
        $this->assertSame('Day program', $visit->service_location_label);
        $this->assertSame('200 Center Ave', $visit->service_address_line_1);
    }

    public function test_lifecycle_change_writes_admin_history_and_dsp_cannot_view_it(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Paused->value,
                'reason' => 'Hospitalization pause',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_admin_history_events', [
            'client_id' => $client->id,
            'category' => ClientAdminHistoryCategory::Lifecycle->value,
            'new_value' => ClientStatus::Paused->label(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.viewAdminHistory', false)
                ->where('admin_history', []));

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.viewAdminHistory', true)
                ->has('admin_history', 1));
    }

    public function test_correction_requires_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-10',
            'status' => CarePlanStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('care-plans.correct-effective-date', $plan), [
                'corrected_starts_on' => '2026-10-01',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, CarePlanEffectiveDateCorrection::query()->count());
        $this->assertSame(0, ClientAdminHistoryEvent::query()
            ->where('category', ClientAdminHistoryCategory::EffectiveDateCorrection)
            ->count());
    }
}
