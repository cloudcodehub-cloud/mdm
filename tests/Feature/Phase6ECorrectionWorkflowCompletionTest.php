<?php

namespace Tests\Feature;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\SkipReason;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase6ECorrectionWorkflowCompletionTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{dsp: Employee, supervisor: Employee, scheduled: ScheduledVisit, visit: Visit}
     */
    private function completedVisit(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-20 09:05:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 17:00:00', 'UTC'),
            'visit_notes' => 'Original notes.',
            'handover_note' => 'Original handover.',
            'clock_in_location_method' => ClockInLocationMethod::BrowserGps,
            'clock_in_location_status' => ClockInLocationStatus::Captured,
            'clock_in_latitude' => 40.1,
            'clock_in_longitude' => -82.1,
        ]);

        return compact('dsp', 'supervisor', 'scheduled', 'visit');
    }

    public function test_supervisor_approves_dsp_correction_in_caseload(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'requested_clocked_out_at' => '2026-10-20T16:45',
                'reason' => 'Phone delayed clock-in.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Verified with client coverage log.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction->refresh();
        $this->assertSame(AttendanceCorrectionStatus::Approved, $correction->status);
        $this->assertSame($supervisor->user()->firstOrFail()->id, $correction->reviewed_by_user_id);
        $this->assertSame('2026-10-20 09:05:00', $visit->fresh()->clocked_in_at?->format('Y-m-d H:i:s'));
        $this->assertTrue(app(AttendanceStatusService::class)->effectiveTimes($scheduled->fresh())['adjusted']);
    }

    public function test_supervisor_rejects_dsp_correction_in_caseload(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_out_at' => '2026-10-20T18:00',
                'reason' => 'Stayed late.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.reject', $correction), [
                'review_note' => 'No coverage evidence.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AttendanceCorrectionStatus::Rejected, $correction->fresh()->status);
        $this->assertSame('2026-10-20 17:00:00', $visit->fresh()->clocked_out_at?->format('Y-m-d H:i:s'));
        $this->assertFalse(app(AttendanceStatusService::class)->effectiveTimes($scheduled->fresh())['adjusted']);
    }

    public function test_supervisor_cannot_approve_own_or_outsider_or_supervisor_initiated(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'scheduled' => $scheduled] = $this->completedVisit();
        $otherSupervisor = Employee::factory()->supervisor()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_visit_notes' => 'Supervisor note fix.',
                'reason' => 'Supervisor-initiated correction.',
            ])
            ->assertRedirect();

        $supervisorInitiated = $scheduled->fresh()->attendanceCorrections()->firstOrFail();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $supervisorInitiated))
            ->assertForbidden();

        $this->actingAs($otherSupervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $supervisorInitiated))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $supervisorInitiated))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AttendanceCorrectionStatus::Approved, $supervisorInitiated->fresh()->status);

        $scheduledB = ScheduledVisit::factory()
            ->forClient(Client::factory()->forSupervisor($supervisor)->create())
            ->forDsp($dsp)
            ->create([
                'service_date' => '2026-10-19',
                'starts_at' => '09:00:00',
                'ends_at' => '17:00:00',
                'supervisor_id' => $supervisor->id,
                'status' => ScheduledVisitStatus::Completed,
            ]);
        Visit::factory()->forScheduledVisit($scheduledB)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-19 09:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-19 17:00:00', 'UTC'),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduledB), [
                'requested_clocked_in_at' => '2026-10-19T09:05',
                'reason' => 'DSP request for outsider check.',
            ])
            ->assertRedirect();

        $dspCorrection = $scheduledB->fresh()->attendanceCorrections()->firstOrFail();
        $this->actingAs($otherSupervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $dspCorrection))
            ->assertForbidden();
    }

    public function test_missed_zero_evv_reconstruction_preserves_missed_and_marks_reconstructed(): void
    {
        Carbon::setTestNow('2026-10-20 20:00:00');
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->assertSame(
            AttendanceStatus::Missed,
            app(AttendanceStatusService::class)->statusFor($scheduled),
        );
        $this->assertNull($scheduled->visit);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'is_evv_reconstruction' => true,
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'requested_clocked_out_at' => '2026-10-20T17:00',
                'requested_visit_notes' => 'Verified coverage reconstruction.',
                'reason' => 'Device failure; service occurred.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);
        $this->assertTrue($correction->is_evv_reconstruction);
        $this->assertSame('missed', $correction->original_attendance_status);
        $this->assertNull($correction->visit_id);
        $this->assertNull($scheduled->fresh()->visit);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Admin confirming material EVV reconstruction.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $scheduled->refresh()->load('visits', 'attendanceCorrections');
        $visit = $scheduled->visits->firstOrFail();
        $correction->refresh();

        $this->assertSame(ScheduledVisitStatus::Completed, $scheduled->status);
        $this->assertTrue($visit->evv_reconstructed);
        $this->assertSame(ClockInLocationStatus::Unavailable, $visit->clock_in_location_status);
        $this->assertSame(ClockInLocationMethod::GpsUnavailable, $visit->clock_in_location_method);
        $this->assertNull($visit->clock_in_latitude);
        $this->assertSame('missed', $correction->original_attendance_status);
        $this->assertSame($visit->id, $correction->visit_id);
        $this->assertSame('2026-10-20 09:00:00', $visit->clocked_in_at?->format('Y-m-d H:i:s'));
    }

    public function test_supervisor_can_approve_dsp_zero_evv_reconstruction_request_is_admin_or_supervisor_only_to_submit(): void
    {
        Carbon::setTestNow('2026-10-20 20:00:00');
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        // DSP cannot create a Missed zero-EVV reconstruction (no completed Visit segment).
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'is_evv_reconstruction' => true,
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'requested_clocked_out_at' => '2026-10-20T17:00',
                'reason' => 'DSP trying to reconstruct Missed.',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'is_evv_reconstruction' => true,
                'requested_clocked_in_at' => '2026-10-20T09:15',
                'requested_clocked_out_at' => '2026-10-20T16:45',
                'reason' => 'Supervisor reconstructing Missed EVV.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();

        // Supervisor-initiated → needs Admin (no self-approval).
        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertForbidden();

        $peer = Employee::factory()->supervisor()->create();
        // Peer not in caseload / not DSP requester.
        $this->actingAs($peer->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($scheduled->fresh()->visits()->firstOrFail()->evv_reconstructed);
    }

    public function test_reconstruction_overlap_blocks_finalize_and_preserves_missed(): void
    {
        Carbon::setTestNow('2026-10-20 20:00:00');
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $missed = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $other = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '12:00:00',
            'ends_at' => '20:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);
        Visit::factory()->forScheduledVisit($other)->create([
            'employee_id' => $dsp->id,
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-20 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 18:00:00', 'UTC'),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->from(route('attendance.show', $missed))
            ->post(route('attendance.corrections.store', $missed), [
                'is_evv_reconstruction' => true,
                'requested_clocked_in_at' => '2026-10-20T11:00',
                'requested_clocked_out_at' => '2026-10-20T14:00',
                'reason' => 'Would overlap existing Visit.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('overlap_conflict');

        $this->assertFalse($missed->fresh()->visits()->exists());
        $this->assertSame(
            AttendanceStatus::Missed,
            app(AttendanceStatusService::class)->statusFor($missed->fresh()),
        );
        $this->assertTrue($missed->fresh()->attendanceCorrections()->firstOrFail()->overlap_conflict);
    }

    public function test_task_status_correction_applies_on_approval_and_preserves_originals(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();
        $skip = SkipReason::factory()->create(['name' => 'Client declined']);
        $task = VisitTask::factory()->for($visit)->create([
            'status' => VisitTaskStatus::Pending,
            'title' => 'Medication reminder',
        ]);
        $completed = VisitTask::factory()->for($visit)->create([
            'status' => VisitTaskStatus::Completed,
            'title' => 'Meal assist',
            'completed_at' => Carbon::parse('2026-10-20 10:00:00', 'UTC'),
            'completion_note' => 'Done at 10.',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'task_corrections' => [
                    [
                        'visit_task_id' => $task->id,
                        'requested_status' => 'completed',
                        'requested_completion_note' => 'Completed after wrap-up clarification.',
                    ],
                    [
                        'visit_task_id' => $completed->id,
                        'requested_status' => 'skipped',
                        'requested_skip_reason_id' => $skip->id,
                        'requested_skip_comment' => 'Client later declined.',
                    ],
                ],
                'reason' => 'Task outcomes need correction after completion.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertContains('tasks', $correction->proposed_fields ?? []);
        $this->assertSame('pending', $correction->task_corrections[0]['original_status']);
        $this->assertSame('completed', $correction->task_corrections[1]['original_status']);
        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);
        $this->assertSame(VisitTaskStatus::Completed, $completed->fresh()->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $task->refresh();
        $completed->refresh();
        $this->assertSame(VisitTaskStatus::Completed, $task->status);
        $this->assertSame('Completed after wrap-up clarification.', $task->completion_note);
        $this->assertSame(VisitTaskStatus::Skipped, $completed->status);
        $this->assertSame($skip->id, $completed->skip_reason_id);
        $this->assertSame('pending', $correction->fresh()->task_corrections[0]['original_status']);
        $this->assertSame('completed', $correction->fresh()->task_corrections[1]['original_status']);
    }

    public function test_dsp_cannot_directly_mutate_completed_visit_tasks_outside_correction(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();
        $task = VisitTask::factory()->for($visit)->create([
            'status' => VisitTaskStatus::Pending,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.tasks.complete', [$visit, $task]))
            ->assertForbidden();

        $this->assertSame(VisitTaskStatus::Pending, $task->fresh()->status);
        $this->assertSame(VisitStatus::Completed, $visit->fresh()->status);
        $this->assertTrue($dsp->user()->firstOrFail()->can('create', [\App\Models\AttendanceCorrection::class, $scheduled]));
    }
}
