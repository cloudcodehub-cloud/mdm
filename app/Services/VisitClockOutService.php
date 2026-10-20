<?php

namespace App\Services;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\GpsFallbackReason;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitClockOutService
{
    public function __construct(
        private VisitExceptionService $exceptions,
        private VisitSafetyCheckService $safetyChecks,
        private ActiveVisitAttentionService $attention,
        private SettingsService $settings,
        private IncidentService $incidents,
        private VisitNoteHistoryService $noteHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateNotes(User $user, Visit $visit, array $payload): Visit
    {
        return DB::transaction(function () use ($user, $visit, $payload): Visit {
            /** @var Visit $visit */
            $visit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $this->assertInProgress($visit);

            $notes = $this->noteHistory->apply($visit, $user, $payload);

            $visit->update([
                'visit_notes' => $notes['visit_notes'],
                'handover_note' => $notes['handover_note'],
            ]);

            return $visit->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function clockOut(User $user, Visit $visit, array $payload): Visit
    {
        $completed = DB::transaction(function () use ($user, $visit, $payload): Visit {
            /** @var Visit $visit */
            $visit = Visit::query()
                ->with(['tasks', 'scheduledVisit', 'safetyChecks'])
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = $user->employee;

            // Archive Pending DSPs may still finish (clock out of) their already-live visit.
            if ($employee === null || ! $employee->canFinishActiveVisit() || $visit->employee_id !== $employee->id) {
                abort(403);
            }

            if ($visit->status === VisitStatus::Completed || $visit->clocked_out_at !== null) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit has already been clocked out.',
                ]);
            }

            $this->assertInProgress($visit);

            $now = $this->settings->now();
            $notes = $this->noteHistory->apply($visit, $user, $payload);
            $handover = $notes['handover_note'];

            if (! filled($handover)) {
                throw ValidationException::withMessages([
                    'handover_note' => 'Enter the Visit Wrap-Up / Handover Note before clocking out.',
                ]);
            }

            $draft = $this->incidents->activeDraftForVisit($visit);

            if ($draft !== null) {
                $submitDraft = (bool) ($payload['submit_incident_draft'] ?? false);
                $discardDraft = (bool) ($payload['discard_incident_draft'] ?? false);

                if ($submitDraft && $discardDraft) {
                    throw ValidationException::withMessages([
                        'incident_draft' => 'Choose either submit or discard for the unfinished Incident draft, not both.',
                    ]);
                }

                if ($submitDraft) {
                    $this->incidents->submitActiveDraft($user, $visit);
                } elseif ($discardDraft) {
                    $this->incidents->discardDraft($user, $draft, 'Discarded during clock-out.');
                } else {
                    throw ValidationException::withMessages([
                        'incident_draft' => 'Resolve the unfinished Incident draft before clocking out (submit, review, or discard).',
                    ]);
                }
            }

            $safetyBlockers = $this->safetyChecks->undocumentedBlockers($visit, $now);

            if ($safetyBlockers !== []) {
                throw ValidationException::withMessages([
                    'safety_checks' => $safetyBlockers[0],
                ]);
            }

            $pendingRequired = $visit->tasks
                ->where('status', VisitTaskStatus::Pending)
                ->where('is_required', true)
                ->values();

            $acknowledged = (bool) ($payload['acknowledge_unfinished_required'] ?? false);

            if ($pendingRequired->isNotEmpty() && ! $acknowledged) {
                throw ValidationException::withMessages([
                    'acknowledge_unfinished_required' => 'Required tasks are unfinished. Acknowledge this before clocking out.',
                ]);
            }

            $extendedReason = array_key_exists('extended_visit_reason', $payload)
                ? $payload['extended_visit_reason']
                : $visit->extended_visit_reason;
            $extendedReason = is_string($extendedReason) ? trim($extendedReason) : null;
            $extendedReason = $extendedReason === '' ? null : $extendedReason;

            if ($this->attention->requiresExtendedVisitReason($visit, $now) && $extendedReason === null) {
                throw ValidationException::withMessages([
                    'extended_visit_reason' => 'Enter a short reason for remaining past the scheduled end.',
                ]);
            }

            $status = ClockInLocationStatus::from((string) $payload['location_status']);
            $captured = $status === ClockInLocationStatus::Captured;
            $unavailableReason = $captured ? null : GpsFallbackReason::storedReasonFrom($payload);
            $reasonCode = isset($payload['gps_fallback_reason'])
                ? GpsFallbackReason::tryFrom((string) $payload['gps_fallback_reason'])
                : null;

            $visit->update([
                'status' => VisitStatus::Completed,
                'clocked_out_at' => $now,
                'clock_out_latitude' => $captured ? $payload['latitude'] : null,
                'clock_out_longitude' => $captured ? $payload['longitude'] : null,
                'clock_out_accuracy' => $captured ? ($payload['accuracy'] ?? null) : null,
                'clock_out_location_method' => ClockInLocationMethod::from((string) $payload['location_method']),
                'clock_out_location_status' => $status,
                'clock_out_unavailable_reason' => $unavailableReason,
                'visit_notes' => $notes['visit_notes'],
                'handover_note' => $handover,
                'extended_visit_reason' => $extendedReason,
                'unfinished_required_acknowledged' => $pendingRequired->isNotEmpty() && $acknowledged,
            ]);

            if (! $captured) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::GpsUnavailable,
                    'Clock-out GPS was not captured.',
                    null,
                    [
                        'location_status' => $status->value,
                        'reason' => $unavailableReason,
                        'reason_code' => $reasonCode?->value,
                        'reason_note' => $payload['gps_fallback_note'] ?? null,
                    ],
                );
            }

            if ($pendingRequired->isNotEmpty()) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::OtherVisitException,
                    'The DSP clocked out with unfinished required tasks.',
                    null,
                    [
                        'task_ids' => $pendingRequired->pluck('id')->all(),
                        'titles' => $pendingRequired->pluck('title')->all(),
                    ],
                );
            }

            if ($this->attention->requiresExtendedVisitReason($visit, $now)) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::ExtendedVisit,
                    'Visit ran 30+ minutes past scheduled end.',
                    null,
                    [
                        'minutes_past_end' => $this->attention->minutesPastScheduledEnd($visit, $now),
                        'reason' => $extendedReason,
                    ],
                );
            }

            return $visit->refresh();
        });

        app(ScheduledVisitCompletionService::class)
            ->completeIfSettled($completed->scheduledVisit()->first() ?? $completed->scheduledVisit);

        // MDM 03: a pending Client/DSP archive completes automatically once the active visit ends.
        app(ArchivePendingService::class)->onVisitCompleted($completed);

        // MDM 03 §10 — apply deferred temporary-coverage removals after the Active Visit ends.
        app(TemporarySupervisorCoverageService::class)->applyDeferredRemovalsAfterVisit($completed);

        return $completed;
    }

    private function assertInProgress(Visit $visit): void
    {
        if (! $visit->isInProgress()) {
            throw ValidationException::withMessages([
                'visit' => 'This visit is no longer in progress.',
            ]);
        }
    }
}
