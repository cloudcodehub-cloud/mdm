<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\SettingsService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitClockInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function gpsPayload(): array
    {
        return [
            'latitude' => 40.1234567,
            'longitude' => -82.9876543,
            'accuracy' => 12.5,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailablePayload(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'unavailable_reason' => 'Browser GPS permission denied for local demo.',
        ];
    }

    public function test_dsp_can_start_own_scheduled_visit(): void
    {
        Carbon::setTestNow('2026-09-10 09:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-01',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Assist with morning ADLs',
            'recurrence' => TaskRecurrence::Daily,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
            'service_type' => 'Personal Care',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame($dsp->id, $visit->employee_id);
        $this->assertSame($client->id, $visit->client_id);
        $this->assertSame('Personal Care', $visit->service_type);
        $this->assertSame(VisitStatus::InProgress, $visit->status);
        $this->assertSame('2026-09-10 09:00:00', $visit->clocked_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(ScheduledVisitStatus::InProgress, $scheduled->fresh()->status);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.client.name', $client->full_name)
                ->where('visit.service_type', 'Personal Care')
                ->has('visit.tasks', 1)
                ->where('visit.tasks.0.title', 'Assist with morning ADLs')
                ->where('visit.tasks.0.status', 'pending')
            );
    }

    public function test_dsp_cannot_start_another_dsps_visit(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($other)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertForbidden();

        $this->assertSame(0, Visit::query()->count());
    }

    public function test_inactive_and_terminated_dsps_cannot_start_visits(): void
    {
        $inactive = Employee::factory()->dsp()->inactive()->create();
        $terminated = Employee::factory()->dsp()->terminated()->create();
        $inactiveVisit = ScheduledVisit::factory()->forDsp($inactive)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);
        $terminatedVisit = ScheduledVisit::factory()->forDsp($terminated)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);

        $this->assertTrue(Gate::forUser($inactive->user()->firstOrFail())->denies('clockIn', $inactiveVisit));
        $this->assertTrue(Gate::forUser($terminated->user()->firstOrFail())->denies('clockIn', $terminatedVisit));

        $this->actingAs($inactive->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $inactiveVisit), $this->unavailablePayload())
            ->assertRedirect(route('login'));

        $this->actingAs($terminated->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $terminatedVisit), $this->unavailablePayload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Visit::query()->count());
        $this->assertContains($inactive->employment_status, [EmploymentStatus::Inactive]);
        $this->assertSame(EmploymentStatus::Terminated, $terminated->employment_status);
    }

    public function test_second_active_visit_is_blocked(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $first = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
            'starts_at' => '07:00:00',
            'ends_at' => '11:00:00',
        ]);
        $second = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
            'starts_at' => '12:00:00',
            'ends_at' => '16:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $first), $this->unavailablePayload())
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('dashboard'))
            ->post(route('scheduled-visits.clock-in', $second), $this->unavailablePayload())
            ->assertSessionHasErrors('scheduled_visit');

        $this->assertSame(1, Visit::query()->count());
        $this->assertSame(ScheduledVisitStatus::Scheduled, $second->fresh()->status);
    }

    public function test_duplicate_request_does_not_duplicate_visit_or_tasks(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'recurrence' => TaskRecurrence::Daily,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = Visit::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect(route('visits.show', $visit));

        $this->actingAs($user)->get(route('visits.show', $visit))->assertOk();
        $this->actingAs($user)->get(route('visits.show', $visit))->assertOk();

        $this->assertSame(1, Visit::query()->count());
        $this->assertSame(1, VisitTask::query()->count());
    }

    public function test_gps_available_data_can_be_stored(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->gpsPayload())
            ->assertRedirect();

        $visit = Visit::query()->firstOrFail();
        $this->assertEqualsWithDelta(40.1234567, (float) $visit->clock_in_latitude, 0.0001);
        $this->assertEqualsWithDelta(-82.9876543, (float) $visit->clock_in_longitude, 0.0001);
        $this->assertSame(ClockInLocationMethod::BrowserGps, $visit->clock_in_location_method);
        $this->assertSame(ClockInLocationStatus::Captured, $visit->clock_in_location_status);
        $this->assertNull($visit->clock_in_unavailable_reason);
    }

    public function test_gps_unavailable_attestation_works(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = Visit::query()->firstOrFail();
        $this->assertNull($visit->clock_in_latitude);
        $this->assertNull($visit->clock_in_longitude);
        $this->assertSame(ClockInLocationMethod::GpsUnavailable, $visit->clock_in_location_method);
        $this->assertSame(ClockInLocationStatus::Denied, $visit->clock_in_location_status);
        $this->assertSame('Browser GPS permission denied for local demo.', $visit->clock_in_unavailable_reason);
    }

    public function test_care_plan_task_instances_are_generated_from_active_templates(): void
    {
        Carbon::setTestNow('2026-09-10 08:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-10',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Daily living skills',
            'recurrence' => TaskRecurrence::Daily,
            'sort_order' => 1,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Weekly community outing',
            'recurrence' => TaskRecurrence::Weekly,
            'sort_order' => 2,
        ]);
        $historical = CarePlan::factory()->inactive()->forClient($client)->create();
        CarePlanTaskTemplate::factory()->forCarePlan($historical)->create([
            'title' => 'Old historical task',
        ]);

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $titles = VisitTask::query()->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame(['Daily living skills', 'Weekly community outing'], $titles);
        $this->assertNotContains('Old historical task', $titles);
    }

    public function test_admin_and_supervisor_visibility_remains_appropriate(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');
        $this->seed(DemoSeeder::class);

        $maya = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $scheduled = ScheduledVisit::query()
            ->where('employee_id', $maya->employee->id)
            ->whereDate('service_date', '2026-09-11')
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->firstOrFail();

        $this->actingAs($maya)
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        $visit = $scheduled->fresh()->visit()->firstOrFail();
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();
        $otherSupervisor = User::query()->where('email', 'priya.nair@mdm.test')->firstOrFail();
        $otherDsp = User::query()->where('email', 'nina.brooks@mdm.test')->firstOrFail();

        $this->actingAs($admin)->get(route('visits.show', $visit))->assertOk();
        $this->actingAs($supervisor)->get(route('visits.show', $visit))->assertOk();
        $this->actingAs($otherSupervisor)->get(route('visits.show', $visit))->assertForbidden();
        $this->actingAs($otherDsp)->get(route('visits.show', $visit))->assertForbidden();

        $this->actingAs($admin)
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertForbidden();
        $this->actingAs($supervisor)
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/show')
                ->where('visit.status', ScheduledVisitStatus::InProgress->value)
                ->where('can.clock_in', false)
                ->where('visit.active_visit_id', $visit->id)
            );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
