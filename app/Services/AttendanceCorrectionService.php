<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 05 §L / 05-02 / 05-04 / 05-05 / 05-07 / 05-22 / MDM 01 §2.44 —
 * unified Visit correction through AttendanceCorrection.
 */
class AttendanceCorrectionService
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceStatusService $attendanceStatuses,
        private VisitNoteHistoryService $noteHistory,
        private CarePlanRevisionService $carePlanRevisions,
        private VisitTaskGenerator $tasks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(ScheduledVisit $scheduledVisit, User $user, array $data): AttendanceCorrection
    {
        $scheduledVisit->loadMissing(['visits.tasks', 'attendanceCorrections', 'employee', 'client']);

        if ($scheduledVisit->attendanceCorrections->contains(
            fn (AttendanceCorrection $correction): bool => $correction->isPending(),
        )) {
            throw ValidationException::withMessages([
                'status' => 'A correction request is already pending for this attendance record.',
            ]);
        }

        $isReconstruction = filter_var($data['is_evv_reconstruction'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $visit = $this->resolveTargetVisit($scheduledVisit, $data['visit_id'] ?? null);
        $isMissedReconstruction = $visit === null && $isReconstruction;

        if ($visit === null && ! $isMissedReconstruction) {
            throw ValidationException::withMessages([
                'visit' => 'A Visit record is required before requesting a correction, unless reconstructing a Missed Scheduled Visit.',
            ]);
        }

        if ($isMissedReconstruction) {
            $this->assertMissedReconstructionAllowed($scheduledVisit, $user);
        }

        $requestedIn = $this->settings->parseLocalDateTime($data['requested_clocked_in_at'] ?? null);
        $requestedOut = $this->settings->parseLocalDateTime($data['requested_clocked_out_at'] ?? null);

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

        $taskCorrections = $this->normalizeTaskCorrections($visit, $data['task_corrections'] ?? null);
        if ($taskCorrections !== []) {
            $proposed[] = 'tasks';
        }

        if ($proposed === []) {
            throw ValidationException::withMessages([
                'requested_clocked_in_at' => 'Provide a corrected time, note, GPS context, task/status, or EVV reconstruction.',
            ]);
        }

        if ($isReconstruction && ($requestedIn === null || $requestedOut === null)) {
            throw ValidationException::withMessages([
                'requested_clocked_in_at' => 'EVV reconstruction requires both reconstructed start and end times.',
            ]);
        }

        foreach (['requested_clock_in_latitude', 'requested_clock_out_latitude', 'latitude', 'longitude'] as $forbidden) {
            if (array_key_exists($forbidden, $data) && $data[$forbidden] !== null && $data[$forbidden] !== '') {
                throw ValidationException::withMessages([
                    'reason' => 'Missing GPS coordinates cannot be manufactured after the fact. Correct GPS context/classification only.',
                ]);
            }
        }

        $effectiveStart = $requestedIn ?? $visit?->clocked_in_at;
        $effectiveEnd = $requestedOut ?? $visit?->effectiveEndedAt();

        if ($effectiveStart !== null && $effectiveEnd !== null && $effectiveEnd->lte($effectiveStart)) {
            throw ValidationException::withMessages([
                'requested_clocked_out_at' => 'Corrected end time must be after the start time.',
            ]);
        }

        $originalAttendance = $isMissedReconstruction
            ? AttendanceStatus::Missed->value
            : $this->attendanceStatuses->statusFor($scheduledVisit)->value;

        $attributes = [
            'scheduled_visit_id' => $scheduledVisit->id,
            'visit_id' => $visit?->id,
            'is_evv_reconstruction' => $isReconstruction,
            'overlap_conflict' => false,
            'proposed_fields' => $proposed,
            'original_attendance_status' => $originalAttendance,
            'task_corrections' => $taskCorrections === [] ? null : $taskCorrections,
            'original_clocked_in_at' => $visit?->clocked_in_at,
            'original_clocked_out_at' => $visit?->clocked_out_at ?? $visit?->administrative_effective_ended_at,
            'requested_clocked_in_at' => $requestedIn,
            'requested_clocked_out_at' => $requestedOut,
            'original_visit_notes' => $visit?->visit_notes,
            'requested_visit_notes' => in_array('visit_notes', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_visit_notes'] ?? null) ? $data['requested_visit_notes'] : null)
                : null,
            'original_handover_note' => $visit?->handover_note,
            'requested_handover_note' => in_array('handover_note', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_handover_note'] ?? null) ? $data['requested_handover_note'] : null)
                : null,
            'original_clock_in_location_status' => $visit?->clock_in_location_status?->value,
            'requested_clock_in_location_status' => in_array('clock_in_gps_context', $proposed, true)
                ? $this->normalizeLocationStatus($data['requested_clock_in_location_status'] ?? null)
                : null,
            'original_clock_in_unavailable_reason' => $visit?->clock_in_unavailable_reason,
            'requested_clock_in_unavailable_reason' => in_array('clock_in_gps_context', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_clock_in_unavailable_reason'] ?? null) ? $data['requested_clock_in_unavailable_reason'] : null)
                : null,
            'original_clock_out_location_status' => $visit?->clock_out_location_status?->value,
            'requested_clock_out_location_status' => in_array('clock_out_gps_context', $proposed, true)
                ? $this->normalizeLocationStatus($data['requested_clock_out_location_status'] ?? null)
                : null,
            'original_clock_out_unavailable_reason' => $visit?->clock_out_unavailable_reason,
            'requested_clock_out_unavailable_reason' => in_array('clock_out_gps_context', $proposed, true)
                ? $this->normalizeNote(is_string($data['requested_clock_out_unavailable_reason'] ?? null) ? $data['requested_clock_out_unavailable_reason'] : null)
                : null,
            'reason' => trim((string) $data['reason']),
            'note' => $this->normalizeNote(is_string($data['note'] ?? null) ? $data['note'] : null),
            'requested_by_user_id' => $user->id,
            'status' => AttendanceCorrectionStatus::Pending,
        ];

        $correction = AttendanceCorrection::query()->create($attributes);

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
        $correction->loadMissing(['visit', 'scheduledVisit.employee', 'scheduledVisit.client']);
        $visit = $correction->visit;

        $effectiveStart = $correction->requested_clocked_in_at ?? $visit?->clocked_in_at;
        $effectiveEnd = $correction->requested_clocked_out_at ?? $visit?->effectiveEndedAt();

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
        ?Visit $visit,
        ?CarbonInterface $effectiveStart,
        ?CarbonInterface $effectiveEnd,
        ?string $reviewNote,
    ): void {
        $proposesTime = $correction->requestsTimeChange();

        if ($correction->isMissedReconstruction() || ($visit === null && $correction->is_evv_reconstruction)) {
            if ($effectiveStart === null || $effectiveEnd === null) {
                throw ValidationException::withMessages([
                    'requested_clocked_out_at' => 'Effective start and end are required to approve this reconstruction.',
                ]);
            }

            $visit = $this->createReconstructedVisit($correction, $effectiveStart, $effectiveEnd);
            $correction->forceFill(['visit_id' => $visit->id])->save();
        }

        if ($visit === null) {
            throw ValidationException::withMessages([
                'visit' => 'The Visit for this correction is missing.',
            ]);
        }

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
        $this->applyTaskCorrections($correction);
    }

    /**
     * MDM 05-05 / 05-07 — create a Completed reconstructed Visit without fabricating
     * Captured DSP GPS. Historical Missed outcome remains on the correction record.
     */
    private function createReconstructedVisit(
        AttendanceCorrection $correction,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Visit {
        $scheduled = $correction->scheduledVisit()->with(['client', 'employee'])->firstOrFail();

        if ($scheduled->status === ScheduledVisitStatus::Cancelled) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'Cancelled scheduled visits cannot be reconstructed into worked service.',
            ]);
        }

        if ($scheduled->visits()->exists()) {
            throw ValidationException::withMessages([
                'visit' => 'This scheduled visit already has Visit evidence; use a linked segment correction instead.',
            ]);
        }

        if ($scheduled->employee_id === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'A responsible DSP assignment is required before reconstructing EVV.',
            ]);
        }

        $probe = new Visit([
            'employee_id' => $scheduled->employee_id,
            'status' => VisitStatus::Completed,
        ]);
        $this->assertNoSameDspOverlap($probe, $start, $end);

        $carePlan = $this->carePlanRevisions->effectivePlanFor(
            $scheduled->client_id,
            $scheduled->service_date,
        );

        $visit = Visit::query()->create([
            'scheduled_visit_id' => $scheduled->id,
            'employee_id' => $scheduled->employee_id,
            'client_id' => $scheduled->client_id,
            'care_plan_id' => $carePlan?->id,
            'service_type' => $scheduled->service_type,
            'status' => VisitStatus::Completed,
            // Required columns; attribution remains reconstructed (never Captured GPS).
            'clocked_in_at' => $start,
            'clocked_out_at' => $end,
            'evv_reconstructed' => true,
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
            'clock_in_unavailable_reason' => 'EVV reconstructed — no original DSP GPS evidence.',
            'clock_out_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_out_location_status' => ClockInLocationStatus::Unavailable,
            'clock_out_unavailable_reason' => 'EVV reconstructed — no original DSP GPS evidence.',
            'visit_notes' => $correction->requested_visit_notes,
            'handover_note' => $correction->requested_handover_note,
        ]);

        $this->tasks->generate($visit);

        $scheduled->update([
            'status' => ScheduledVisitStatus::Completed,
            'handoff_pending' => false,
        ]);

        return $visit;
    }

    private function assertMissedReconstructionAllowed(ScheduledVisit $scheduledVisit, User $user): void
    {
        if (! $user->isAdmin() && ! $user->isSupervisor()) {
            throw ValidationException::withMessages([
                'is_evv_reconstruction' => 'Only authorized Supervisors or Admins may reconstruct a Missed Visit with no EVV.',
            ]);
        }

        if ($scheduledVisit->status === ScheduledVisitStatus::Cancelled) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'Cancelled scheduled visits cannot be reconstructed into worked service.',
            ]);
        }

        if ($scheduledVisit->visits->isNotEmpty()) {
            throw ValidationException::withMessages([
                'visit' => 'This scheduled visit already has Visit evidence.',
            ]);
        }

        if ($scheduledVisit->employee_id === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Assign a responsible DSP before reconstructing EVV for this Missed visit.',
            ]);
        }

        $status = $this->attendanceStatuses->statusFor($scheduledVisit);
        if ($status !== AttendanceStatus::Missed) {
            throw ValidationException::withMessages([
                'is_evv_reconstruction' => 'Zero-EVV reconstruction applies after the Scheduled Visit is Missed (no Visit started).',
            ]);
        }
    }

    private function applyVisitSideEffects(AttendanceCorrection $correction, Visit $visit, User $actor): void
    {
        $updates = [];

        if ($correction->proposes('visit_notes')) {
            $this->noteHistory->recordApprovedCorrectionChange(
                $visit,
                $actor,
                VisitNoteHistoryService::FIELD_VISIT_NOTES,
                $visit->visit_notes,
                $correction->requested_visit_notes,
                $correction->reason,
            );
            $updates['visit_notes'] = $correction->requested_visit_notes;
        }

        if ($correction->proposes('handover_note')) {
            $this->noteHistory->recordApprovedCorrectionChange(
                $visit,
                $actor,
                VisitNoteHistoryService::FIELD_HANDOVER_NOTE,
                $visit->handover_note,
                $correction->requested_handover_note,
                $correction->reason,
            );
            $updates['handover_note'] = $correction->requested_handover_note;
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

    private function applyTaskCorrections(AttendanceCorrection $correction): void
    {
        if (! $correction->requestsTaskChange()) {
            return;
        }

        foreach ($correction->task_corrections ?? [] as $row) {
            $task = VisitTask::query()
                ->whereKey((int) ($row['visit_task_id'] ?? 0))
                ->where('visit_id', $correction->visit_id)
                ->first();

            if ($task === null) {
                continue;
            }

            $status = VisitTaskStatus::tryFrom((string) ($row['requested_status'] ?? ''));
            if ($status === null) {
                continue;
            }

            $task->forceFill([
                'status' => $status,
                'completed_at' => $status === VisitTaskStatus::Completed
                    ? ($task->completed_at ?? now())
                    : null,
                'skipped_at' => $status === VisitTaskStatus::Skipped
                    ? ($task->skipped_at ?? now())
                    : null,
                'skip_reason_id' => $status === VisitTaskStatus::Skipped
                    ? ($row['requested_skip_reason_id'] ?? null)
                    : null,
                'skip_comment' => $status === VisitTaskStatus::Skipped
                    ? ($row['requested_skip_comment'] ?? null)
                    : null,
                'completion_note' => $status === VisitTaskStatus::Completed
                    ? ($row['requested_completion_note'] ?? $task->completion_note)
                    : $task->completion_note,
            ])->save();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeTaskCorrections(?Visit $visit, mixed $rows): array
    {
        if ($visit === null || ! is_array($rows) || $rows === []) {
            return [];
        }

        $visit->loadMissing('tasks');
        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $taskId = (int) ($row['visit_task_id'] ?? 0);
            $task = $visit->tasks->first(fn (VisitTask $candidate): bool => $candidate->id === $taskId);
            if ($task === null) {
                throw ValidationException::withMessages([
                    'task_corrections' => 'Each task correction must reference a task on this Visit.',
                ]);
            }

            $requested = VisitTaskStatus::tryFrom((string) ($row['requested_status'] ?? ''));
            if ($requested === null || ! in_array($requested, [
                VisitTaskStatus::Completed,
                VisitTaskStatus::Skipped,
                VisitTaskStatus::Pending,
            ], true)) {
                throw ValidationException::withMessages([
                    'task_corrections' => 'Task corrections may only request Complete, Skip, or Pending.',
                ]);
            }

            if ($requested === VisitTaskStatus::Skipped && empty($row['requested_skip_reason_id'])) {
                throw ValidationException::withMessages([
                    'task_corrections' => 'Skipped tasks require a skip reason.',
                ]);
            }

            $normalized[] = [
                'visit_task_id' => $task->id,
                'original_status' => $task->status->value,
                'requested_status' => $requested->value,
                'original_skip_reason_id' => $task->skip_reason_id,
                'requested_skip_reason_id' => $requested === VisitTaskStatus::Skipped
                    ? (int) $row['requested_skip_reason_id']
                    : null,
                'original_skip_comment' => $task->skip_comment,
                'requested_skip_comment' => $requested === VisitTaskStatus::Skipped
                    ? $this->normalizeNote(isset($row['requested_skip_comment']) ? (string) $row['requested_skip_comment'] : null)
                    : null,
                'original_completion_note' => $task->completion_note,
                'requested_completion_note' => $requested === VisitTaskStatus::Completed
                    ? $this->normalizeNote(isset($row['requested_completion_note']) ? (string) $row['requested_completion_note'] : null)
                    : null,
            ];
        }

        return $normalized;
    }

    private function assertNoSameDspOverlap(
        Visit $visit,
        CarbonInterface $start,
        CarbonInterface $end,
    ): void {
        $query = Visit::query()
            ->with(['scheduledVisit.attendanceCorrections'])
            ->where('employee_id', $visit->employee_id)
            ->whereIn('status', [VisitStatus::InProgress->value, VisitStatus::Completed->value]);

        if ($visit->exists) {
            $query->whereKeyNot($visit->id);
        }

        foreach ($query->get() as $other) {
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
