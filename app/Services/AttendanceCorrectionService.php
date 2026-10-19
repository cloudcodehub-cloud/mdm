<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\ClockInLocationStatus;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 05 §L / 05-02 / 05-04 / 05-05 / 05-22 — unified Visit correction through
 * AttendanceCorrection. Pending never becomes effective; Visit EVV clock rows stay
 * immutable; approved overlays and side-effects apply only after authorization.
 */
class AttendanceCorrectionService
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceStatusService $attendanceStatuses,
        private VisitNoteHistoryService $noteHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(ScheduledVisit $scheduledVisit, User $user, array $data): AttendanceCorrection
    {
        $scheduledVisit->loadMissing(['visits', 'attendanceCorrections']);
        $visit = $this->resolveTargetVisit($scheduledVisit, $data['visit_id'] ?? null);

        if ($visit === null) {
            throw ValidationException::withMessages([
                'visit' => 'A Visit record is required before requesting a correction.',
            ]);
        }

        if ($scheduledVisit->attendanceCorrections->contains(
            fn (AttendanceCorrection $correction): bool => $correction->isPending(),
        )) {
            throw ValidationException::withMessages([
                'status' => 'A correction request is already pending for this attendance record.',
            ]);
        }

        $requestedIn = $this->settings->parseLocalDateTime($data['requested_clocked_in_at'] ?? null);
        $requestedOut = $this->settings->parseLocalDateTime($data['requested_clocked_out_at'] ?? null);
        $isReconstruction = filter_var($data['is_evv_reconstruction'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $proposed = [];
        if ($requestedIn !== null || $requestedOut !== null) {
            $proposed[] = 'times';
        }
        if (array_key_exists('requested_visit_notes', $data)) {
            $proposed[] = 'visit_notes';
        }
        if (array_key_exists('requested_handover_note', $data)) {
            $proposed[] = 'handover_note';
        }
        if (array_key_exists('requested_clock_in_location_status', $data)
            || array_key_exists('requested_clock_in_unavailable_reason', $data)) {
            $proposed[] = 'clock_in_gps_context';
        }
        if (array_key_exists('requested_clock_out_location_status', $data)
            || array_key_exists('requested_clock_out_unavailable_reason', $data)) {
            $proposed[] = 'clock_out_gps_context';
        }
        if ($isReconstruction) {
            $proposed[] = 'evv_reconstruction';
        }

        if ($proposed === []) {
            throw ValidationException::withMessages([
                'requested_clocked_in_at' => 'Provide a corrected time, note, GPS context change, or EVV reconstruction.',
            ]);
        }

        if ($isReconstruction && ($requestedIn === null || $requestedOut === null)) {
            throw ValidationException::withMessages([
                'requested_clocked_in_at' => 'EVV reconstruction requires both reconstructed start and end times.',
            ]);
        }

        // MDM 05-02 — never accept manufactured coordinates through correction.
        foreach (['requested_clock_in_latitude', 'requested_clock_out_latitude', 'latitude', 'longitude'] as $forbidden) {
            if (array_key_exists($forbidden, $data) && $data[$forbidden] !== null && $data[$forbidden] !== '') {
                throw ValidationException::withMessages([
                    'reason' => 'Missing GPS coordinates cannot be manufactured after the fact. Correct GPS context/classification only.',
                ]);
            }
        }

        $effectiveStart = $requestedIn ?? $visit->clocked_in_at;
        $effectiveEnd = $requestedOut ?? $visit->effectiveEndedAt();

        if ($effectiveStart !== null && $effectiveEnd !== null && $effectiveEnd->lte($effectiveStart)) {
            throw ValidationException::withMessages([
                'requested_clocked_out_at' => 'Corrected end time must be after the start time.',
            ]);
        }

        $attributes = [
            'scheduled_visit_id' => $scheduledVisit->id,
            'visit_id' => $visit->id,
            'is_evv_reconstruction' => $isReconstruction,
            'overlap_conflict' => false,
            'proposed_fields' => $proposed,
            'original_clocked_in_at' => $visit->clocked_in_at,
            'original_clocked_out_at' => $visit->clocked_out_at ?? $visit->administrative_effective_ended_at,
            'requested_clocked_in_at' => $requestedIn,
            'requested_clocked_out_at' => $requestedOut,
            'original_visit_notes' => $visit->visit_notes,
            'requested_visit_notes' => in_array('visit_notes', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_visit_notes'] ?? null) ? $data['requested_visit_notes'] : null)
                : null,
            'original_handover_note' => $visit->handover_note,
            'requested_handover_note' => in_array('handover_note', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_handover_note'] ?? null) ? $data['requested_handover_note'] : null)
                : null,
            'original_clock_in_location_status' => $visit->clock_in_location_status?->value,
            'requested_clock_in_location_status' => in_array('clock_in_gps_context', $proposed, true)
                ? $this->normalizeLocationStatus($data['requested_clock_in_location_status'] ?? null)
                : null,
            'original_clock_in_unavailable_reason' => $visit->clock_in_unavailable_reason,
            'requested_clock_in_unavailable_reason' => in_array('clock_in_gps_context', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_clock_in_unavailable_reason'] ?? null) ? $data['requested_clock_in_unavailable_reason'] : null)
                : null,
            'original_clock_out_location_status' => $visit->clock_out_location_status?->value,
            'requested_clock_out_location_status' => in_array('clock_out_gps_context', $proposed, true)
                ? $this->normalizeLocationStatus($data['requested_clock_out_location_status'] ?? null)
                : null,
            'original_clock_out_unavailable_reason' => $visit->clock_out_unavailable_reason,
            'requested_clock_out_unavailable_reason' => in_array('clock_out_gps_context', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_clock_out_unavailable_reason'] ?? null) ? $data['requested_clock_out_unavailable_reason'] : null)
                : null,
            'reason' => trim((string) $data['reason']),
            'note' => $this->normalizeNote(is_string($data['note'] ?? null) ? $data['note'] : null),
            'requested_by_user_id' => $user->id,
        ];

        $attributes['status'] = AttendanceCorrectionStatus::Pending;

        $correction = AttendanceCorrection::query()->create($attributes);

        // Admin may apply directly, but MDM 05-04 overlap still cannot silently finalize —
        // the request remains pending with an overlap conflict flag.
        if ($user->isAdmin()) {
            try {
                return DB::transaction(function () use ($correction, $user, $visit, $effectiveStart, $effectiveEnd, $data): AttendanceCorrection {
                    $this->finalizeApproval(
                        $correction,
                        $user,
                        $visit,
                        $effectiveStart,
                        $effectiveEnd,
                        $this->normalizeNote(is_string($data['note'] ?? null) ? $data['note'] : null),
                    );

                    return $correction->refresh();
                });
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['overlap_conflict'])) {
                    $correction->forceFill(['overlap_conflict' => true])->save();
                }

                throw $exception;
            }
        }

        return $correction->refresh();
    }

    public function approve(AttendanceCorrection $correction, User $user, ?string $reviewNote = null): AttendanceCorrection
    {
        $this->assertPending($correction);
        $correction->loadMissing('visit');
        $visit = $correction->visit;

        if ($visit === null) {
            throw ValidationException::withMessages([
                'visit' => 'The Visit for this correction is missing.',
            ]);
        }

        $effectiveStart = $correction->requested_clocked_in_at ?? $visit->clocked_in_at;
        $effectiveEnd = $correction->requested_clocked_out_at ?? $visit->effectiveEndedAt();

        try {
            return DB::transaction(function () use ($correction, $user, $visit, $effectiveStart, $effectiveEnd, $reviewNote): AttendanceCorrection {
                $this->finalizeApproval($correction, $user, $visit, $effectiveStart, $effectiveEnd, $reviewNote);

                return $correction->refresh();
            });
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['overlap_conflict'])) {
                $correction->forceFill(['overlap_conflict' => true])->save();
            }

            throw $exception;
        }
    }

    public function reject(AttendanceCorrection $correction, User $user, ?string $reviewNote = null): AttendanceCorrection
    {
        $this->assertPending($correction);

        $correction->forceFill([
            'status' => AttendanceCorrectionStatus::Rejected,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $this->normalizeNote($reviewNote),
            'overlap_conflict' => false,
        ])->save();

        return $correction->refresh();
    }

    private function finalizeApproval(
        AttendanceCorrection $correction,
        User $user,
        Visit $visit,
        ?CarbonInterface $effectiveStart,
        ?CarbonInterface $effectiveEnd,
        ?string $reviewNote,
    ): void {
        $proposesTime = $correction->proposes('times') || $correction->proposes('evv_reconstruction');

        if ($proposesTime) {
            if ($effectiveStart === null || $effectiveEnd === null) {
                throw ValidationException::withMessages([
                    'requested_clocked_out_at' => 'Effective start and end are required to approve this correction.',
                ]);
            }

            $this->assertNoSameDspOverlap($visit, $effectiveStart, $effectiveEnd);
        }

        $correction->forceFill([
            'status' => AttendanceCorrectionStatus::Approved,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $this->normalizeNote($reviewNote),
            'overlap_conflict' => false,
            'applied_at' => now(),
        ])->save();

        $this->applyVisitSideEffects($correction, $visit, $user);
    }

    private function applyVisitSideEffects(AttendanceCorrection $correction, Visit $visit, User $actor): void
    {
        $updates = [];

        if ($correction->proposes('visit_notes')) {
            $previous = $visit->visit_notes;
            $next = $correction->requested_visit_notes;
            $this->noteHistory->recordApprovedCorrectionChange(
                $visit,
                $actor,
                VisitNoteHistoryService::FIELD_VISIT_NOTES,
                $previous,
                $next,
                $correction->reason,
            );
            $updates['visit_notes'] = $next;
        }

        if ($correction->proposes('handover_note')) {
            $previous = $visit->handover_note;
            $next = $correction->requested_handover_note;
            $this->noteHistory->recordApprovedCorrectionChange(
                $visit,
                $actor,
                VisitNoteHistoryService::FIELD_HANDOVER_NOTE,
                $previous,
                $next,
                $correction->reason,
            );
            $updates['handover_note'] = $next;
        }

        if ($correction->proposes('clock_in_gps_context')) {
            if ($correction->requested_clock_in_location_status !== null) {
                $updates['clock_in_location_status'] = ClockInLocationStatus::from(
                    $correction->requested_clock_in_location_status,
                );
            }
            $updates['clock_in_unavailable_reason'] = $correction->requested_clock_in_unavailable_reason;
        }

        if ($correction->proposes('clock_out_gps_context')) {
            if ($correction->requested_clock_out_location_status !== null) {
                $updates['clock_out_location_status'] = ClockInLocationStatus::from(
                    $correction->requested_clock_out_location_status,
                );
            }
            $updates['clock_out_unavailable_reason'] = $correction->requested_clock_out_unavailable_reason;
        }

        if ($correction->is_evv_reconstruction || $correction->proposes('evv_reconstruction')) {
            $updates['evv_reconstructed'] = true;
        }

        if ($updates !== []) {
            $visit->forceFill($updates)->save();
        }
    }

    /**
     * MDM 05-04 — proposed effective window must not overlap another Visit for the same DSP.
     */
    private function assertNoSameDspOverlap(
        Visit $visit,
        CarbonInterface $start,
        CarbonInterface $end,
    ): void {
        $others = Visit::query()
            ->with(['scheduledVisit.attendanceCorrections'])
            ->where('employee_id', $visit->employee_id)
            ->whereKeyNot($visit->id)
            ->whereIn('status', [VisitStatus::InProgress->value, VisitStatus::Completed->value])
            ->get();

        foreach ($others as $other) {
            $window = $this->attendanceStatuses->segmentEffectiveWindow($other);
            if ($window['start'] === null || $window['end'] === null) {
                continue;
            }

            if ($start->lt($window['end']) && $end->gt($window['start'])) {
                throw ValidationException::withMessages([
                    'requested_clocked_in_at' => 'This correction would create overlapping effective Visits for the same DSP and cannot be finalized. Resolve the conflict before approval.',
                    'overlap_conflict' => 'Unresolved same-DSP Visit overlap.',
                ]);
            }
        }
    }

    private function resolveTargetVisit(ScheduledVisit $scheduledVisit, mixed $visitId): ?Visit
    {
        if ($visitId !== null && $visitId !== '') {
            $visit = $scheduledVisit->visits->first(
                fn (Visit $candidate): bool => $candidate->id === (int) $visitId,
            );

            if ($visit === null) {
                throw ValidationException::withMessages([
                    'visit_id' => 'Select a Visit segment that belongs to this scheduled visit.',
                ]);
            }

            return $visit;
        }

        return $scheduledVisit->activeVisit()
            ?? $scheduledVisit->visits->sortByDesc('id')->first()
            ?? $scheduledVisit->visit;
    }

    private function assertPending(AttendanceCorrection $correction): void
    {
        if (! $correction->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending correction requests can be reviewed.',
            ]);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }

    private function normalizeLocationStatus(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $status = ClockInLocationStatus::tryFrom((string) $value);
        if ($status === null) {
            throw ValidationException::withMessages([
                'requested_clock_in_location_status' => 'Select a valid GPS/location status.',
            ]);
        }

        return $status->value;
    }
}
