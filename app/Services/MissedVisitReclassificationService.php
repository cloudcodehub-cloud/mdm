<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ScheduledVisitStatus;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\VisitOutcomeReclassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 05 Decision 05-10 — verified retrospective Missed → Cancelled.
 * Does not manufacture attendance or worked hours. Original Missed history is preserved.
 */
class MissedVisitReclassificationService
{
    public function __construct(
        private AttendanceStatusService $attendanceStatuses,
        private PayrollFinalizationService $payroll,
        private MaterialCorrectionAttentionService $attention,
        private SchedulingNotificationService $scheduleNotifications,
    ) {}

    /**
     * @param  array{reason: string, evidence_note?: string|null}  $payload
     */
    public function reclassifyMissedToCancelled(
        ScheduledVisit $scheduledVisit,
        User $actor,
        array $payload,
    ): VisitOutcomeReclassification {
        $scheduledVisit->loadMissing(['visits', 'employee', 'client']);

        if (! $actor->isAdmin() && ! $actor->isSupervisor()) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'Only authorized Supervisors or Admins may reclassify Missed to Cancelled.',
            ]);
        }

        if (! $actor->can('update', $scheduledVisit)) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'You are not authorized to reclassify this Scheduled Visit.',
            ]);
        }

        $this->payroll->assertNotLockedForCorrection($scheduledVisit);

        if ($scheduledVisit->status === ScheduledVisitStatus::Cancelled) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'This Scheduled Visit is already Cancelled.',
            ]);
        }

        if ($scheduledVisit->visits->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'A started Visit cannot become Cancelled. Worked hours remain on the actual Visit record.',
            ]);
        }

        $status = $this->attendanceStatuses->statusFor($scheduledVisit);

        if ($status !== AttendanceStatus::Missed) {
            throw ValidationException::withMessages([
                'scheduled_visit' => 'Only a Scheduled Visit currently classified as Missed may be reclassified to Cancelled.',
            ]);
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A verified cancellation reason is required.',
            ]);
        }

        $evidence = $this->normalizeNote($payload['evidence_note'] ?? null);
        $now = now();

        $reclassification = DB::transaction(function () use ($scheduledVisit, $actor, $reason, $evidence, $now): VisitOutcomeReclassification {
            $record = VisitOutcomeReclassification::query()->create([
                'scheduled_visit_id' => $scheduledVisit->id,
                'from_outcome' => AttendanceStatus::Missed->value,
                'to_outcome' => ScheduledVisitStatus::Cancelled->value,
                'reason' => $reason,
                'evidence_note' => $evidence,
                'reclassified_by_user_id' => $actor->id,
                'reclassified_at' => $now,
            ]);

            $scheduledVisit->forceFill([
                'status' => ScheduledVisitStatus::Cancelled,
                'cancelled_at' => $now,
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => $reason,
                'updated_by_user_id' => $actor->id,
                'needs_attention' => false,
                'handoff_pending' => false,
                'staffing_needed' => false,
            ])->save();

            return $record;
        });

        $this->scheduleNotifications->visitCancelled($scheduledVisit->fresh(['employee.user', 'client']));
        $this->attention->notifyMissedToCancelled($reclassification->fresh(['scheduledVisit.client', 'scheduledVisit.employee', 'reclassifiedBy']));

        return $reclassification->refresh();
    }

    private function normalizeNote(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
