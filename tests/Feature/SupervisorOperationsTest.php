<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupervisorOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_supervisor_operations_board_is_scoped_to_caseload(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->seed(DemoSeeder::class);

        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();
        $otherSupervisor = User::query()->where('email', 'priya.nair@mdm.test')->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('operations.today', '2026-09-11')
                ->where('operations.metrics.0.key', 'assigned_dsps')
                ->where('operations.metrics.0.value', 2)
                ->where('operations.metrics.1.key', 'assigned_clients')
                ->where('operations.metrics.1.value', 3)
                ->has('operations.today_visits')
                ->has('operations.active_visits')
                ->has('operations.completed_visits')
                ->has('operations.exceptions.gps')
            );

        $this->actingAs($otherSupervisor)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('operations.metrics.0.value', 2)
            );
    }

    public function test_late_status_uses_operational_timezone(): void
    {
        Carbon::setTestNow('2026-09-11 16:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('operations.today_visits.0.operational_status', 'late')
            );
    }

    public function test_supervisor_can_monitor_permitted_visit_without_altering_clock_events(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => '2026-09-11 09:05:00',
            'visit_notes' => 'DSP note',
            'handover_note' => 'Handover for next DSP',
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Denied,
        ]);
        $scheduled->update(['status' => ScheduledVisitStatus::InProgress]);
        VisitException::factory()->create([
            'visit_id' => $visit->id,
            'type' => VisitExceptionType::GpsUnavailable,
            'message' => 'GPS was not captured at clock-in.',
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.employee.name', $dsp->full_name)
                ->where('visit.client.name', $client->full_name)
                ->where('visit.handover_note', 'Handover for next DSP')
                ->where('can.record_tasks', false)
                ->where('can.clock_out', false)
                ->where('can.update_notes', false)
                ->has('visit.exceptions', 1)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), [
                'visit_notes' => 'Supervisor rewrite',
                'handover_note' => 'Changed',
            ])
            ->assertForbidden();

        $this->assertSame('DSP note', $visit->fresh()->visit_notes);
        $this->assertSame('Handover for next DSP', $visit->fresh()->handover_note);
        $this->assertSame('2026-09-11 09:05:00', $visit->fresh()->clocked_in_at->format('Y-m-d H:i:s'));
    }

    public function test_dsp_and_out_of_scope_supervisor_cannot_open_operations(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($dsp)->get(route('operations.index'))->assertForbidden();
        $this->actingAs($dsp)->get(route('visit-exceptions.index'))->assertForbidden();
        $this->actingAs($dsp)->get(route('supervisors.index'))->assertForbidden();
    }
}
