<?php

namespace Tests\Feature;

use App\Enums\CarePlanStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\TaskRecurrence;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\CarePlanRevisionService;
use App\Services\SettingsService;
use App\Services\VisitTaskGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase2ACarePlanRevisionTest extends TestCase
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

    public function test_immediate_revision_does_not_rewrite_active_visit_snapshot(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
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

        $revision = app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-10-16',
            'revision_reason' => 'Add afternoon medication support',
            'tasks' => [[
                'title' => 'Afternoon medication support',
                'recurrence' => TaskRecurrence::Daily->value,
                'is_required' => true,
                'can_skip' => true,
                'note_required' => false,
                'is_critical' => false,
            ]],
        ], $supervisor->user()->firstOrFail());

        $this->assertSame(CarePlanStatus::Active, $revision->status);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame(CarePlanStatus::Inactive, $plan->fresh()->status);

        app(VisitTaskGenerator::class)->generate($visit->fresh());

        $titles = VisitTask::query()->where('visit_id', $visit->id)->pluck('title')->all();
        $this->assertSame(['Morning ADLs'], $titles);
        $this->assertSame($plan->id, $visit->fresh()->care_plan_id);
        $this->assertNotSame($revision->id, $visit->fresh()->care_plan_id);
    }

    public function test_future_effective_revision_keeps_current_plan_until_start_date(): void
    {
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Current task',
            'recurrence' => TaskRecurrence::Daily,
        ]);

        $pending = app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-10-20',
            'revision_reason' => 'Winter schedule',
            'tasks' => [[
                'title' => 'Winter task',
                'recurrence' => TaskRecurrence::Daily->value,
                'is_required' => true,
                'can_skip' => true,
                'note_required' => false,
                'is_critical' => false,
            ]],
        ]);

        $this->assertSame(CarePlanStatus::Pending, $pending->status);
        $this->assertSame(CarePlanStatus::Active, $plan->fresh()->status);
        $this->assertSame('2026-10-19', $plan->fresh()->ends_on?->toDateString());

        $today = app(CarePlanRevisionService::class)->effectivePlanFor($client, '2026-10-16');
        $this->assertSame($plan->id, $today?->id);

        $future = app(CarePlanRevisionService::class)->effectivePlanFor($client, '2026-10-20');
        $this->assertSame($pending->id, $future?->id);
        $this->assertSame(CarePlanStatus::Active, $pending->fresh()->status);
        $this->assertSame(CarePlanStatus::Inactive, $plan->fresh()->status);
    }

    public function test_routine_revisions_cannot_be_backdated(): void
    {
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create();

        $this->expectException(ValidationException::class);

        app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-10-01',
            'tasks' => [[
                'title' => 'Backdated task',
                'recurrence' => TaskRecurrence::Daily->value,
            ]],
        ]);
    }

    public function test_only_one_pending_future_revision_is_kept(): void
    {
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Base',
            'recurrence' => TaskRecurrence::Daily,
        ]);

        $first = app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-10-25',
            'tasks' => [['title' => 'First pending', 'recurrence' => TaskRecurrence::Daily->value]],
        ]);
        $second = app(CarePlanRevisionService::class)->revise($plan->fresh(), [
            'effective_on' => '2026-10-28',
            'tasks' => [['title' => 'Second pending', 'recurrence' => TaskRecurrence::Daily->value]],
        ]);

        $this->assertSame(CarePlanStatus::Inactive, $first->fresh()->status);
        $this->assertSame(CarePlanStatus::Pending, $second->fresh()->status);
        $this->assertSame(1, CarePlan::query()->where('client_id', $client->id)->pending()->count());
    }

    public function test_pending_revision_can_be_cancelled_before_effective(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Base',
            'recurrence' => TaskRecurrence::Daily,
        ]);

        $pending = app(CarePlanRevisionService::class)->revise($plan, [
            'effective_on' => '2026-11-01',
            'tasks' => [['title' => 'Pending task', 'recurrence' => TaskRecurrence::Daily->value]],
        ], $supervisor->user()->firstOrFail());

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('care-plans.cancel-pending', $pending))
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame(CarePlanStatus::Inactive, $pending->fresh()->status);
        $this->assertNull($plan->fresh()->ends_on);
        $this->assertSame($plan->id, app(CarePlanRevisionService::class)->effectivePlanFor($client)?->id);
    }

    public function test_supervisor_can_revise_caseload_care_plan_but_not_client_master_or_authorization(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $foreign = Client::factory()->forSupervisor($other)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Base',
            'recurrence' => TaskRecurrence::Daily,
        ]);
        $auth = ClientAuthorization::factory()->forClient($client)->create();

        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)
            ->put(route('care-plans.tasks.sync', $plan), [
                'effective_on' => '2026-10-16',
                'revision_reason' => 'Caseload update',
                'tasks' => [[
                    'title' => 'Updated caseload task',
                    'recurrence' => TaskRecurrence::Daily->value,
                    'is_required' => true,
                    'can_skip' => true,
                    'note_required' => false,
                    'is_critical' => false,
                ]],
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertTrue(
            CarePlan::query()
                ->where('client_id', $client->id)
                ->where('revision_number', 2)
                ->where('status', CarePlanStatus::Active)
                ->exists()
        );

        $this->actingAs($user)
            ->put(route('care-plans.tasks.sync', CarePlan::factory()->forClient($foreign)->create()), [
                'tasks' => [['title' => 'Nope', 'recurrence' => TaskRecurrence::Daily->value]],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('clients.update', $client), [
                'first_name' => 'Changed',
                'last_name' => $client->last_name,
                'status' => $client->status->value,
            ])
            ->assertForbidden();

        $this->assertFalse($user->can('update', $auth));
    }

    public function test_clock_in_binds_effective_care_plan_revision(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Bound task',
            'recurrence' => TaskRecurrence::Daily,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
        ]);

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame($plan->id, $visit->care_plan_id);
        $this->assertSame(VisitStatus::InProgress, $visit->status);
        $this->assertSame('Bound task', VisitTask::query()->where('visit_id', $visit->id)->value('title'));
    }

    public function test_historical_care_plan_tasks_remain_immutable(): void
    {
        $client = Client::factory()->create();
        $historical = CarePlan::factory()->inactive()->forClient($client)->create();
        CarePlanTaskTemplate::factory()->forCarePlan($historical)->create([
            'title' => 'Old task',
        ]);

        $this->expectException(ValidationException::class);

        app(CarePlanRevisionService::class)->syncTasksOrRevise($historical, [
            ['title' => 'Rewrite history', 'recurrence' => TaskRecurrence::Daily->value],
        ]);
    }
}
