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
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitNoteHistoryEvent;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase6DUnifiedVisitCorrectionsTest extends TestCase
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
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-20 09:05:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 17:00:00', 'UTC'),
            'visit_notes' => 'Original notes.',
            'handover_note' => 'Original handover.',
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
            'clock_in_unavailable_reason' => 'GPS weak at intake.',
        ]);

        return compact('dsp', 'supervisor', 'scheduled', 'visit');
    }

    public function test_pending_correction_does_not_change_effective_attendance(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'requested_clocked_out_at' => '2026-10-20T16:00',
                'reason' => 'Phone delayed clock-in.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);
        $this->assertSame('2026-10-20 09:05:00', $visit->fresh()->clocked_in_at?->format('Y-m-d H:i:s'));

        $attendance = app(AttendanceStatusService::class);
        $this->assertSame(AttendanceStatus::Completed, $attendance->statusFor($scheduled->fresh()));
        $this->assertFalse($attendance->effectiveTimes($scheduled->fresh())['adjusted']);
        $this->assertSame(475, $attendance->workedMinutes($scheduled->fresh()));
    }

    public function test_approved_note_and_gps_context_apply_without_mutating_original_clocks_or_coords(): void
    {
        ['supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();
        $admin = User::factory()->admin()->create();
        $originalLat = $visit->clock_in_latitude;

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_visit_notes' => 'Corrected clinical note.',
                'requested_handover_note' => 'Corrected handover.',
                'requested_clock_in_unavailable_reason' => 'Attested GPS unavailable indoors.',
                'reason' => 'DSP mistyped notes after wrap-up.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame('Corrected clinical note.', $visit->visit_notes);
        $this->assertSame('Corrected handover.', $visit->handover_note);
        $this->assertSame('Attested GPS unavailable indoors.', $visit->clock_in_unavailable_reason);
        $this->assertSame($originalLat, $visit->clock_in_latitude);
        $this->assertSame('2026-10-20 09:05:00', $visit->clocked_in_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Original notes.', $correction->fresh()->original_visit_notes);
        $this->assertTrue(
            VisitNoteHistoryEvent::query()->where('visit_id', $visit->id)->where('field_key', 'visit_notes')->exists(),
        );
    }

    public function test_evv_reconstruction_marks_visit_and_never_masquerades_as_dsp_evv(): void
    {
        ['scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'requested_clocked_out_at' => '2026-10-20T17:00',
                'is_evv_reconstruction' => true,
                'reason' => 'Device lost; reconstruct from verified coverage.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertTrue($visit->evv_reconstructed);
        $this->assertSame('2026-10-20 09:05:00', $visit->clocked_in_at?->format('Y-m-d H:i:s'));
        $this->assertTrue(
            $scheduled->fresh()->attendanceCorrections()->firstOrFail()->is_evv_reconstruction,
        );
        $this->assertTrue(app(AttendanceStatusService::class)->effectiveTimes($scheduled->fresh())['adjusted']);
    }

    public function test_manufactured_gps_coordinates_are_rejected(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled] = $this->completedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('attendance.show', $scheduled))
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-20T09:00',
                'latitude' => 40.1,
                'longitude' => -82.1,
                'reason' => 'Trying to invent GPS.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');
    }

    public function test_same_dsp_overlap_blocks_approval_and_flags_conflict(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedVisit();
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $other = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '12:00:00',
            'ends_at' => '20:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        Visit::factory()->forScheduledVisit($other)->create([
            'employee_id' => $dsp->id,
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-20 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 18:00:00', 'UTC'),
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-20T11:00',
                'requested_clocked_out_at' => '2026-10-20T14:00',
                'reason' => 'Would overlap other Visit.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->actingAs($admin)
            ->from(route('attendance.show', $scheduled))
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertRedirect()
            ->assertSessionHasErrors('overlap_conflict');

        $correction->refresh();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);
        $this->assertTrue($correction->overlap_conflict);
        $this->assertSame('2026-10-20 09:05:00', $visit->fresh()->clocked_in_at?->format('Y-m-d H:i:s'));
        $this->assertFalse(app(AttendanceStatusService::class)->effectiveTimes($scheduled->fresh())['adjusted']);
    }

    public function test_linked_segment_correction_does_not_double_count_or_overwrite_sibling(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $supervisor = Employee::factory()->supervisor()->create();
        $dspA = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $dspB = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dspB)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dspA)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);

        $segmentA = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $dspA->id,
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-20 09:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 12:00:00', 'UTC'),
        ]);
        $segmentB = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $dspB->id,
            'status' => VisitStatus::Completed,
            'replaced_visit_id' => $segmentA->id,
            'clocked_in_at' => Carbon::parse('2026-10-20 13:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-20 17:00:00', 'UTC'),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('attendance.corrections.store', $scheduled), [
                'visit_id' => $segmentA->id,
                'requested_clocked_out_at' => '2026-10-20T11:30',
                'reason' => 'Segment A ended earlier.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $attendance = app(AttendanceStatusService::class);
        $scheduled->refresh()->load('visits', 'attendanceCorrections');
        // 09:00–11:30 (150) + 13:00–17:00 (240) = 390; gap unserved.
        $this->assertSame(390, $attendance->workedMinutes($scheduled));
        $this->assertSame('2026-10-20 13:00:00', $segmentB->fresh()->clocked_in_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 12:00:00', $segmentA->fresh()->clocked_out_at?->format('Y-m-d H:i:s'));

        $windowA = $attendance->segmentEffectiveWindow($segmentA->fresh());
        $this->assertSame('2026-10-20 11:30:00', $windowA['end']?->format('Y-m-d H:i:s'));
    }

    public function test_handoff_clock_in_still_enforces_compliance_and_assignee_gates(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $outsider = Employee::factory()->dsp()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($replacement)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'latitude' => 40.1,
                'longitude' => -82.1,
                'accuracy' => 12,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
            ])
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $visit), [
                'employee_id' => $replacement->id,
                'effective_ended_at' => '2026-10-20T12:00',
                'reason' => 'Mid-service replacement.',
            ])
            ->assertRedirect();

        $scheduled->refresh();
        $this->assertTrue($scheduled->handoff_pending);

        // Wrong assignee cannot use handoff_pending bypass.
        $this->actingAs($outsider->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'latitude' => 40.1,
                'longitude' => -82.1,
                'accuracy' => 12,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
            ])
            ->assertForbidden();

        // Cancelled never startable even if handoff_pending were stuck true.
        $scheduled->update(['status' => ScheduledVisitStatus::Cancelled]);
        $this->actingAs($replacement->user()->firstOrFail())
            ->from(route('scheduled-visits.show', $scheduled))
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'latitude' => 40.1,
                'longitude' => -82.1,
                'accuracy' => 12,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('scheduled_visit');
    }
}
