<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 05-03 / MASTER §10.5 — Administrative Close establishes an effective end
 * without fabricating DSP clock-out or GPS evidence. Completes this Visit segment
 * only; linked handoff Visits (MDM 05 §10.6–10.7) remain a later concern.
 */
class VisitAdministrativeCloseService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array{effective_ended_at: string, reason: string}  $payload
     */
    public function close(User $actor, Visit $visit, array $payload): Visit
    {
        $completed = DB::transaction(function () use ($actor, $visit, $payload): Visit {
            /** @var Visit $visit */
            $visit = Visit::query()
                ->with(['scheduledVisit'])
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($visit->isAdministrativelyClosed() || $visit->status === VisitStatus::Completed) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit has already been closed.',
                ]);
            }

            if (! $visit->isInProgress()) {
                throw ValidationException::withMessages([
                    'visit' => 'Only an active visit can be administratively closed.',
                ]);
            }

            // Never impersonate DSP EVV: clocked_out_at and clock-out GPS stay null.
            if ($visit->clocked_out_at !== null) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit already has a DSP clock-out.',
                ]);
            }

            $now = $this->settings->now();
            $effectiveEnd = $this->settings->parseLocalDateTime(
                is_string($payload['effective_ended_at'])
                    ? $payload['effective_ended_at']
                    : (string) $payload['effective_ended_at'],
            );
            $reason = trim((string) $payload['reason']);

            if ($effectiveEnd === null) {
                throw ValidationException::withMessages([
                    'effective_ended_at' => 'Enter the effective service end time.',
                ]);
            }

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Enter a reason for administrative close.',
                ]);
            }

            if ($effectiveEnd->lt($visit->clocked_in_at)) {
                throw ValidationException::withMessages([
                    'effective_ended_at' => 'Effective end cannot be before the DSP clock-in.',
                ]);
            }

            if ($effectiveEnd->gt($now)) {
                throw ValidationException::withMessages([
                    'effective_ended_at' => 'Effective end cannot be in the future.',
                ]);
            }

            $visit->update([
                'status' => VisitStatus::Completed,
                'administrative_effective_ended_at' => $effectiveEnd,
                'administratively_closed_at' => $now,
                'administratively_closed_by_user_id' => $actor->id,
                'administrative_close_reason' => $reason,
            ]);

            $visit->scheduledVisit()->update([
                'status' => ScheduledVisitStatus::Completed,
            ]);

            return $visit->refresh();
        });

        app(ArchivePendingService::class)->onVisitCompleted($completed);

        return $completed;
    }
}
