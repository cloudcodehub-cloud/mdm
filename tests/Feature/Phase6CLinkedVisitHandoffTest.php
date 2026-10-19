<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitAssignmentKind;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase6CLinkedVisitHandoffTest extends TestCase
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
     * @return array{
     *     dsp: Employee,
     *     replacement: Employee,
     *     supervisor: Employee,
     *     visit: Visit,
     *     scheduled: ScheduledVisit,
     *     client: Client
     * }
     */
    private function startedVisitWithReplacementCandidate(): array
    {
        Carbon::setTestNow('2026-10-20 09:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($replacement)->forClient($client)->create();

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->locationPayload())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();

        return compact('dsp', 'replacement', 'supervisor', 'visit', 'scheduled', 'client');
    }

    /**
     * @return array<string, mixed>
     */
    private function locationPayload(): array
    {
        return [
            'latitude' => 40.1,
            'longitude' => -82.1,
            'accuracy' => 12,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handoffPayload(Employee $replacement, string $endedAt = '2026-10-20T12:00'): array
    {
        return [
            'employee_id' => $replacement->id,
            'effective_ended_at' => $endedAt,
            'reason' => 'Original DSP left mid-service; authorized replacement assigned.',
        ];
    }

    public function test_supervisor_handoff_preserves_original_evv_and_keeps_scheduled_open(): void
    {
        [
            'dsp' => $dsp,
            'replacement' => $replacement,
            'supervisor' => $supervisor,
            'visit' => $visit,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        $originalLat = $visit->clock_in_latitude;
        $originalLng = $visit->clock_in_longitude;
        $visit->update([
            'visit_notes' => 'Morning meds given.',
            'handover_note' => 'Client preferred quiet afternoon.',
        ]);

        Carbon::setTestNow('2026-10-20 12:30:00');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $visit), $this->handoffPayload($replacement))
            ->assertRedirect(route('scheduled-visits.show', $scheduled));

        $visit->refresh();
        $scheduled->refresh();

        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertTrue($visit->isAdministrativelyClosed());
        $this->assertNull($visit->clocked_out_at);
        $this->assertNull($visit->clock_out_latitude);
        $this->assertNull($visit->clock_out_longitude);
        $this->assertSame($originalLat, $visit->clock_in_latitude);
        $this->assertSame($originalLng, $visit->clock_in_longitude);
        $this->assertSame('Morning meds given.', $visit->visit_notes);
        $this->assertSame('Client preferred quiet afternoon.', $visit->handover_note);
        $this->assertSame($dsp->id, $visit->employee_id);
        $this->assertNotNull($visit->handed_off_at);
        $this->assertSame($supervisor->user()->firstOrFail()->id, $visit->handed_off_by_user_id);

        $this->assertSame(ScheduledVisitStatus::InProgress, $scheduled->status);
        $this->assertTrue($scheduled->handoff_pending);
        $this->assertSame($replacement->id, $scheduled->employee_id);
        $this->assertTrue(
            $scheduled->assignments()->where('kind', VisitAssignmentKind::Replacement)->exists(),
        );
        $this->assertSame(1, Visit::query()->where('scheduled_visit_id', $scheduled->id)->count());

        $attendance = app(AttendanceStatusService::class);
        $this->assertSame(AttendanceStatus::InProgress, $attendance->statusFor($scheduled->fresh()));
    }

    public function test_replacement_clock_in_creates_linked_visit_with_gap_unserved(): void
    {
        [
            'replacement' => $replacement,
            'supervisor' => $supervisor,
            'visit' => $outgoing,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $outgoing), $this->handoffPayload($replacement, '2026-10-20T12:00'))
            ->assertRedirect();

        Carbon::setTestNow('2026-10-20 12:45:00');
        $this->actingAs($replacement->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->locationPayload())
            ->assertRedirect();

        $segments = Visit::query()
            ->where('scheduled_visit_id', $scheduled->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $segments);
        $incoming = $segments->last();
        $outgoing->refresh();
        $scheduled->refresh();

        $this->assertSame($outgoing->id, $incoming->replaced_visit_id);
        $this->assertTrue($incoming->isLinkedHandoffSegment());
        $this->assertSame($replacement->id, $incoming->employee_id);
        $this->assertSame(VisitStatus::InProgress, $incoming->status);
        $this->assertFalse($scheduled->handoff_pending);
        $this->assertSame(ScheduledVisitStatus::InProgress, $scheduled->status);

        $gap = VisitException::query()
            ->where('visit_id', $incoming->id)
            ->where('type', VisitExceptionType::HandoffCoverageGap)
            ->first();
        $this->assertNotNull($gap);
        $this->assertSame(45, $gap->context['gap_minutes'] ?? null);

        // Gap must not be fabricated into worked minutes while a segment is open.
        $this->assertNull(app(AttendanceStatusService::class)->workedMinutes($scheduled->fresh()));
    }

    public function test_linked_segments_sum_worked_minutes_without_double_counting_gap(): void
    {
        [
            'replacement' => $replacement,
            'supervisor' => $supervisor,
            'visit' => $outgoing,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $outgoing), $this->handoffPayload($replacement, '2026-10-20T12:00'))
            ->assertRedirect();

        Carbon::setTestNow('2026-10-20 13:00:00');
        $this->actingAs($replacement->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->locationPayload())
            ->assertRedirect();

        $incoming = Visit::query()
            ->where('scheduled_visit_id', $scheduled->id)
            ->where('replaced_visit_id', $outgoing->id)
            ->firstOrFail();

        Carbon::setTestNow('2026-10-20 16:00:00');
        $this->actingAs($replacement->user()->firstOrFail())
            ->post(route('visits.clock-out', $incoming), [
                ...$this->locationPayload(),
                'handover_note' => 'Shift covered through end.',
            ])
            ->assertRedirect();

        $scheduled->refresh();
        $this->assertSame(ScheduledVisitStatus::Completed, $scheduled->status);
        $this->assertFalse($scheduled->handoff_pending);
        $this->assertCount(2, $scheduled->visits);

        $attendance = app(AttendanceStatusService::class);
        // 09:00–12:00 (180) + 13:00–16:00 (180) = 360; 60-minute gap unserved.
        $this->assertSame(360, $attendance->workedMinutes($scheduled->fresh()));
        $this->assertSame(AttendanceStatus::Exception, $attendance->statusFor($scheduled->fresh()));
    }

    public function test_cannot_start_second_segment_without_authorized_handoff(): void
    {
        [
            'dsp' => $dsp,
            'replacement' => $replacement,
            'visit' => $visit,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        Carbon::setTestNow('2026-10-20 15:00:00');
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), [
                ...$this->locationPayload(),
                'handover_note' => 'Normal completion.',
            ])
            ->assertRedirect();

        $this->assertSame(ScheduledVisitStatus::Completed, $scheduled->fresh()->status);

        $scheduled->update([
            'employee_id' => $replacement->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($replacement->user()->firstOrFail())
            ->from(route('scheduled-visits.show', $scheduled))
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->locationPayload())
            ->assertRedirect()
            ->assertSessionHasErrors('scheduled_visit');

        $this->assertSame(1, Visit::query()->where('scheduled_visit_id', $scheduled->id)->count());
    }

    public function test_inactive_replacement_and_unauthorized_actors_are_blocked(): void
    {
        [
            'dsp' => $dsp,
            'replacement' => $replacement,
            'supervisor' => $supervisor,
            'visit' => $visit,
        ] = $this->startedVisitWithReplacementCandidate();

        $inactive = Employee::factory()->dsp()->forSupervisor($supervisor)->terminated()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        Carbon::setTestNow('2026-10-20 12:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.handoff', $visit), $this->handoffPayload($replacement))
            ->assertForbidden();

        $this->actingAs($otherSupervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $visit), $this->handoffPayload($replacement))
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.handoff', $visit), $this->handoffPayload($inactive))
            ->assertRedirect()
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertFalse($visit->fresh()->isAdministrativelyClosed());
    }

    public function test_admin_close_without_handoff_still_completes_single_visit_scheduled(): void
    {
        [
            'supervisor' => $supervisor,
            'visit' => $visit,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        Carbon::setTestNow('2026-10-20 16:00:00');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.administrative-close', $visit), [
                'effective_ended_at' => '2026-10-20T15:30',
                'reason' => 'Device failure; no replacement needed.',
            ])
            ->assertRedirect(route('visits.show', $visit));

        $this->assertSame(ScheduledVisitStatus::Completed, $scheduled->fresh()->status);
        $this->assertFalse($scheduled->fresh()->handoff_pending);
        $this->assertSame(1, Visit::query()->where('scheduled_visit_id', $scheduled->id)->count());
    }

    public function test_visit_show_exposes_hand_off_capability(): void
    {
        [
            'dsp' => $dsp,
            'supervisor' => $supervisor,
            'visit' => $visit,
        ] = $this->startedVisitWithReplacementCandidate();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.hand_off', true)
                ->where('can.administratively_close', true)
                ->has('handoff_dsps'));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.hand_off', false));
    }

    public function test_scheduled_visit_show_exposes_segments_after_linked_handoff(): void
    {
        [
            'replacement' => $replacement,
            'supervisor' => $supervisor,
            'visit' => $outgoing,
            'scheduled' => $scheduled,
        ] = $this->startedVisitWithReplacementCandidate();

        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.handoff', $outgoing), $this->handoffPayload($replacement))
            ->assertRedirect();

        Carbon::setTestNow('2026-10-20 12:30:00');
        $this->actingAs($replacement->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->locationPayload())
            ->assertRedirect();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('visit.handoff_pending', false)
                ->has('visit.visit_segments', 2)
                ->where('can.clock_in', false));
    }
}
