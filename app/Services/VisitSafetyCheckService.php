<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\VisitExceptionType;
use App\Enums\VisitSafetyCheckStatus;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\InAppNotification;
use App\Models\Visit;
use App\Models\VisitSafetyCheck;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VisitSafetyCheckService
{
    public function __construct(
        private SettingsService $settings,
        private VisitExceptionService $exceptions,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * Snapshot standing Care Plan supervision onto the Visit at clock-in.
     *
     * @return array{safety_check_interval_minutes: int|null, supervision_instruction: string|null}
     */
    public function snapshotForVisit(Visit $visit): array
    {
        $plan = $this->activeCarePlanFor($visit);
        $allowed = config('mdm.safety_check.allowed_intervals', [5, 10, 15, 30]);
        $interval = $plan?->timed_check_interval_minutes;

        if ($interval !== null && ! in_array((int) $interval, $allowed, true)) {
            $interval = null;
        }

        $instruction = filled($plan?->supervision_instruction)
            ? trim((string) $plan->supervision_instruction)
            : null;

        return [
            'safety_check_interval_minutes' => $interval !== null ? (int) $interval : null,
            'supervision_instruction' => $instruction === '' ? null : $instruction,
        ];
    }

    public function confirmGeneral(Visit $visit, ?CarbonInterface $now = null): Visit
    {
        $this->assertInProgress($visit);
        $now = $now ?? $this->settings->now();

        if ($visit->general_safety_confirmed_at === null) {
            $visit->forceFill(['general_safety_confirmed_at' => $now])->save();
        }

        return $visit->refresh();
    }

    /**
     * Advance due/late/missed states and notify once per check.
     *
     * @return array{created: int, late: int, missed: int, notified: int}
     */
    public function evaluateVisit(Visit $visit, ?CarbonInterface $now = null): array
    {
        $now = $now ?? $this->settings->now();
        $counts = ['created' => 0, 'late' => 0, 'missed' => 0, 'notified' => 0];

        if (! $visit->isInProgress() || ! $visit->hasTimedSafetyChecks()) {
            return $counts;
        }

        $interval = (int) $visit->safety_check_interval_minutes;
        $grace = (int) config('mdm.safety_check.grace_minutes', 2);
        $clockedIn = $visit->clocked_in_at->copy()->timezone($this->settings->timezone());
        $elapsed = max(0, $clockedIn->diffInMinutes($now, false));
        $maxSequence = (int) floor($elapsed / $interval);

        for ($sequence = 1; $sequence <= $maxSequence; $sequence++) {
            $check = $this->ensureCheck($visit, $sequence, $clockedIn->copy()->addMinutes($sequence * $interval));

            if ($check->wasRecentlyCreated) {
                $counts['created']++;
            }

            if ($this->maybeMarkLate($check, $now, $grace)) {
                $counts['late']++;
            }

            if ($this->notifyDue($visit, $check, $now, $grace)) {
                $counts['notified']++;
            }
        }

        foreach ($visit->safetyChecks()->get() as $check) {
            if ($this->maybeMarkMissed($visit, $check, $now, $interval)) {
                $counts['missed']++;
            }
        }

        return $counts;
    }

    /**
     * @return array{created: int, late: int, missed: int, notified: int}
     */
    public function dispatchDue(?CarbonInterface $now = null): array
    {
        $now = $now ?? $this->settings->now();
        $totals = ['created' => 0, 'late' => 0, 'missed' => 0, 'notified' => 0];

        Visit::query()
            ->inProgress()
            ->whereNotNull('safety_check_interval_minutes')
            ->where('safety_check_interval_minutes', '>', 0)
            ->with(['employee.user', 'client', 'scheduledVisit', 'safetyChecks'])
            ->orderBy('id')
            ->each(function (Visit $visit) use ($now, &$totals): void {
                $result = $this->evaluateVisit($visit, $now);
                foreach ($result as $key => $value) {
                    $totals[$key] += $value;
                }
            });

        return $totals;
    }

    /**
     * @param  array{reason?: string|null}  $payload
     */
    public function acknowledge(Visit $visit, VisitSafetyCheck $check, array $payload, ?CarbonInterface $now = null): VisitSafetyCheck
    {
        $this->assertInProgress($visit);

        if ($check->visit_id !== $visit->id) {
            abort(404);
        }

        $now = $now ?? $this->settings->now();
        $grace = (int) config('mdm.safety_check.grace_minutes', 2);
        $graceEnds = $check->due_at->copy()->addMinutes($grace);
        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;
        $reason = $reason === '' ? null : $reason;

        if ($check->status === VisitSafetyCheckStatus::Missed) {
            if ($reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Enter a short reason for this missed safety check.',
                ]);
            }

            $check->forceFill([
                'outcome_reason' => $reason,
                'completed_at' => $check->completed_at ?? $now,
            ])->save();

            return $check->refresh();
        }

        if ($check->isDocumented() && $check->status === VisitSafetyCheckStatus::Completed) {
            return $check;
        }

        if ($check->status === VisitSafetyCheckStatus::Late && $check->completed_at !== null) {
            return $check;
        }

        if ($now->lte($graceEnds)) {
            $check->forceFill([
                'status' => VisitSafetyCheckStatus::Completed,
                'completed_at' => $now,
                'outcome_reason' => null,
            ])->save();

            return $check->refresh();
        }

        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Enter a short reason for completing this safety check late.',
            ]);
        }

        $check->forceFill([
            'status' => VisitSafetyCheckStatus::Late,
            'completed_at' => $now,
            'outcome_reason' => $reason,
        ])->save();

        return $check->refresh();
    }

    /**
     * @return list<string>
     */
    public function undocumentedBlockers(Visit $visit, ?CarbonInterface $now = null): array
    {
        $now = $now ?? $this->settings->now();
        $blockers = [];

        if ($visit->requiresGeneralSafetyConfirmation() && $visit->general_safety_confirmed_at === null) {
            $blockers[] = 'Confirm the visit-level safety acknowledgment before clock-out.';
        }

        if (! $visit->hasTimedSafetyChecks()) {
            return $blockers;
        }

        $this->evaluateVisit($visit, $now);
        $visit->load('safetyChecks');

        foreach ($visit->safetyChecks as $check) {
            if (! $check->isDocumented()) {
                $blockers[] = 'Document safety check #'.$check->sequence.' ('.$check->status->label().') before clock-out.';
            } elseif ($check->status->requiresReason() && blank($check->outcome_reason)) {
                $blockers[] = 'Add a reason for safety check #'.$check->sequence.'.';
            }
        }

        return $blockers;
    }

    private function ensureCheck(Visit $visit, int $sequence, CarbonInterface $dueAt): VisitSafetyCheck
    {
        /** @var VisitSafetyCheck $check */
        $check = VisitSafetyCheck::query()->firstOrCreate(
            [
                'visit_id' => $visit->id,
                'sequence' => $sequence,
            ],
            [
                'due_at' => $dueAt,
                'status' => VisitSafetyCheckStatus::Pending,
            ],
        );

        return $check;
    }

    private function maybeMarkLate(VisitSafetyCheck $check, CarbonInterface $now, int $grace): bool
    {
        if ($check->status !== VisitSafetyCheckStatus::Pending) {
            return false;
        }

        if ($now->lte($check->due_at->copy()->addMinutes($grace))) {
            return false;
        }

        $check->forceFill(['status' => VisitSafetyCheckStatus::Late])->save();

        return true;
    }

    private function maybeMarkMissed(
        Visit $visit,
        VisitSafetyCheck $check,
        CarbonInterface $now,
        int $interval,
    ): bool {
        if (! in_array($check->status, [VisitSafetyCheckStatus::Pending, VisitSafetyCheckStatus::Late], true)) {
            return false;
        }

        if ($check->completed_at !== null) {
            return false;
        }

        // Frozen MDM 01 §2.21: incomplete when the next scheduled check is due → Missed.
        if ($now->lt($check->due_at->copy()->addMinutes($interval))) {
            return false;
        }

        $check->forceFill(['status' => VisitSafetyCheckStatus::Missed])->save();

        $this->exceptions->record(
            $visit,
            VisitExceptionType::MissedSafetyCheck,
            'Safety check #'.$check->sequence.' was missed.',
            null,
            [
                'sequence' => $check->sequence,
                'due_at' => $check->due_at->toIso8601String(),
            ],
            $check,
        );

        return true;
    }

    private function notifyDue(Visit $visit, VisitSafetyCheck $check, CarbonInterface $now, int $grace): bool
    {
        if ($check->completed_at !== null || $check->status === VisitSafetyCheckStatus::Missed) {
            return false;
        }

        if ($now->lt($check->due_at)) {
            return false;
        }

        $user = $visit->employee?->user;

        if ($user === null) {
            return false;
        }

        $late = $now->gt($check->due_at->copy()->addMinutes($grace));
        $title = $late ? 'Safety check overdue' : 'Safety check due';
        $sourceKey = sprintf('visit.%d.safety-check.%d.due', $visit->id, $check->sequence);

        $existing = InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('source_key', $sourceKey)
            ->first();
        $previousTitle = $existing?->title;

        $this->notifications->notify(
            $user,
            InAppNotificationType::Schedule,
            $title,
            sprintf(
                '%s · Safety check #%d is %s.',
                $visit->client->full_name,
                $check->sequence,
                $late ? 'overdue' : 'due now',
            ),
            $sourceKey,
            route('visits.show', $visit),
        );

        return $existing === null || ($previousTitle !== null && $previousTitle !== $title);
    }

    private function activeCarePlanFor(Visit $visit): ?CarePlan
    {
        if ($visit->care_plan_id !== null) {
            $bound = $visit->relationLoaded('carePlan')
                ? $visit->carePlan
                : CarePlan::query()->find($visit->care_plan_id);

            if ($bound !== null) {
                return $bound;
            }
        }

        $serviceDate = $visit->scheduledVisit?->service_date?->toDateString()
            ?? $this->settings->today();

        return app(CarePlanRevisionService::class)->effectivePlanFor($visit->client_id, $serviceDate);
    }

    private function assertInProgress(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages([
                'visit' => 'This visit is no longer in progress.',
            ]);
        }
    }
}
