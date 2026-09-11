<?php

namespace Tests\Feature;

use App\Enums\AvailabilityRequestType;
use App\Enums\InAppNotificationType;
use App\Enums\ReviewStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitAssignmentKind;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\DspAvailabilityRequest;
use App\Models\DspWeeklyAvailability;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5BSchedulingOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_availability_request_requires_approval_and_flags_conflicting_visits(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        DspWeeklyAvailability::factory()->create([
            'employee_id' => $dsp->id,
            'weekday' => (int) now()->startOfDay()->dayOfWeek,
            'is_available' => true,
            'starts_at' => '07:00:00',
            'ends_at' => '23:00:00',
        ]);

        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('my-availability.store'), [
                'type' => AvailabilityRequestType::Weekly->value,
                'effective_on' => now()->toDateString(),
                'reason' => 'School pickup',
                'payload' => [
                    'days' => [[
                        'weekday' => (int) now()->addDay()->dayOfWeek,
                        'is_available' => false,
                    ]],
                ],
            ])
            ->assertRedirect(route('my-availability.index'));

        $request = DspAvailabilityRequest::query()->firstOrFail();
        $this->assertSame(ReviewStatus::Pending, $request->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('availability-requests.approve', $request), [
                'review_note' => 'Approved with coverage flag',
            ])
            ->assertRedirect();

        $this->assertSame(ReviewStatus::Approved, $request->fresh()->status);
        $this->assertTrue($visit->fresh()->needs_attention);
        $this->assertNotNull(InAppNotification::query()->where('type', InAppNotificationType::Availability)->first());
    }

    public function test_leave_blocks_scheduling_and_stays_separate_from_weekly_availability(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        EmployeeTimeOff::factory()->create([
            'employee_id' => $dsp->id,
            'starts_on' => '2026-09-22',
            'ends_on' => '2026-09-22',
            'status' => ReviewStatus::Approved,
            'requested_by_user_id' => $dsp->user_id,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00',
                'ends_at' => '13:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_repeat_creates_series_and_replacement_keeps_history(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $other = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ClientDspAssignment::factory()->forDsp($other)->forClient($client)->create();

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-14',
                'starts_at' => '09:00',
                'ends_at' => '13:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'repeat' => '1',
                'repeat_pattern' => 'weekly',
                'repeat_count' => 3,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $visits = ScheduledVisit::query()->where('client_id', $client->id)->orderBy('service_date')->get();
        $this->assertCount(3, $visits);
        $this->assertNotNull($visits[0]->series_id);
        $this->assertSame($visits[0]->series_id, $visits[2]->series_id);

        $first = $visits[0];
        $this->actingAs($admin)
            ->post(route('scheduled-visits.replace', $first), [
                'employee_id' => $other->id,
                'reason' => 'Call-off',
                'mark_call_off' => '1',
            ])
            ->assertRedirect(route('scheduled-visits.show', $first));

        $first->refresh();
        $this->assertSame($other->id, $first->employee_id);
        $this->assertSame(2, $first->assignments()->count());
        $this->assertTrue($first->assignments()->where('kind', VisitAssignmentKind::Replacement)->exists());
        $this->assertTrue($dsp->availabilityExceptions()->whereDate('exception_date', $first->service_date->toDateString())->exists());
    }

    public function test_calendar_and_availability_board_are_scoped(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ClientAuthorization::factory()->forClient($client)->create([
            'service_type' => 'Personal Care',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-15',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('scheduled-visits.calendar', ['view' => 'week', 'date' => '2026-09-15', 'group' => 'dsp']))
            ->assertOk();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('scheduled-visits.availability-board', [
                'client_id' => $client->id,
                'service_date' => '2026-09-16',
                'starts_at' => '15:00',
                'ends_at' => '23:00',
                'service_type' => 'Personal Care',
            ]))
            ->assertOk()
            ->assertJsonPath('supervisor.id', $supervisor->id);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('scheduled-visits.calendar'))
            ->assertOk();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('availability-requests.index'))
            ->assertForbidden();
    }

    public function test_dsp_cannot_schedule_others(): void
    {
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('scheduled-visits.create'))
            ->assertForbidden();
    }
}
