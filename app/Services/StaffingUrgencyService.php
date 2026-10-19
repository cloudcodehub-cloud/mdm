<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Models\ScheduledVisit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * MDM 04-29: unresolved Staffing Needed becomes more prominent as service time approaches.
 *
 * - Future Staffing Needed is Supervisor-owned; no Admin escalation while it is still distant.
 * - Once now >= scheduled_start - Agency urgency threshold (hours) the visit is URGENT.
 * - At/past scheduled start the visit is OVERDUE (MDM 04-05 Overdue Staffing Needed). This is a
 *   derived presentation state (no extra severity level); a Staffing Needed visit has no Visit
 *   record, so there is no VisitException row to attach to.
 * - Admins receive exactly one escalation per Staffing Needed episode
 *   (scheduled_visits.staffing_urgency_escalated_at), cleared when the visit is staffed.
 *
 * All wall-clock math goes through SettingsService (Agency Operational Timezone, DST-aware).
 */
class StaffingUrgencyService
{
    public const STATE_NONE = 'none';

    public const STATE_UPCOMING = 'upcoming';

    public const STATE_URGENT = 'urgent';

    public const STATE_OVERDUE = 'overdue';

    public function __construct(
        private SettingsService $settings,
        private SchedulingNotificationService $notifications,
    ) {}

    public function thresholdHours(): int
    {
        return $this->settings->staffingNeededUrgencyHours();
    }

    /**
     * Scheduled start as an instant, interpreting service_date + start time in the Agency timezone.
     */
    public function scheduledStart(ScheduledVisit $visit): ?CarbonInterface
    {
        if ($visit->shift_template_id !== null) {
            $visit->loadMissing('shiftTemplate');
        }

        try {
            return $visit->startsAtOn();
        } catch (RuntimeException) {
            return null;
        }
    }

    public function isUnresolved(ScheduledVisit $visit): bool
    {
        return $visit->status === ScheduledVisitStatus::Scheduled
            && $visit->isStaffingNeeded();
    }

    /**
     * @return self::STATE_*
     */
    public function stateFor(ScheduledVisit $visit, ?CarbonInterface $now = null): string
    {
        if (! $this->isUnresolved($visit)) {
            return self::STATE_NONE;
        }

        $start = $this->scheduledStart($visit);

        if ($start === null) {
            return self::STATE_NONE;
        }

        $now = $this->settings->now($now);

        if ($now->gte($start)) {
            return self::STATE_OVERDUE;
        }

        if ($now->gte($start->copy()->subHours($this->thresholdHours()))) {
            return self::STATE_URGENT;
        }

        return self::STATE_UPCOMING;
    }

    public function isUrgent(ScheduledVisit $visit, ?CarbonInterface $now = null): bool
    {
        return in_array($this->stateFor($visit, $now), [self::STATE_URGENT, self::STATE_OVERDUE], true);
    }

    /**
     * @return array{state: string, is_urgent: bool, is_overdue: bool, label: string|null}
     */
    public function presentation(ScheduledVisit $visit, ?CarbonInterface $now = null): array
    {
        $state = $this->stateFor($visit, $now);

        return [
            'state' => $state,
            'is_urgent' => $state === self::STATE_URGENT || $state === self::STATE_OVERDUE,
            'is_overdue' => $state === self::STATE_OVERDUE,
            'label' => match ($state) {
                self::STATE_OVERDUE => 'Overdue Staffing Needed',
                self::STATE_URGENT => 'Urgent Staffing Needed',
                default => null,
            },
        ];
    }

    /**
     * Cheap SQL pre-filter: unresolved visits whose service date could already be inside the
     * threshold. Exact urgency is decided per row in PHP (timezone / shift-template aware).
     *
     * @param  Builder<ScheduledVisit>  $query
     * @return Builder<ScheduledVisit>
     */
    public function candidateQuery(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $localNow = $this->settings->localNow($now);
        $latestDate = $localNow->addHours($this->thresholdHours())->addDay()->toDateString();

        return (clone $query)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->where(function (Builder $builder): void {
                $builder->where('staffing_needed', true)->orWhereNull('employee_id');
            })
            ->whereDate('service_date', '>=', $localNow->toDateString())
            ->whereDate('service_date', '<=', $latestDate)
            ->with(['client', 'employee', 'shiftTemplate', 'visit.tasks', 'visit.exceptions'])
            ->orderBy('service_date')
            ->orderBy('id');
    }

    /**
     * Urgent/overdue Staffing Needed visits within a scoped query (soonest first).
     *
     * @param  Builder<ScheduledVisit>  $query
     * @return Collection<int, ScheduledVisit>
     */
    public function urgentVisits(Builder $query, ?CarbonInterface $now = null): Collection
    {
        return $this->candidateQuery($query, $now)
            ->get()
            ->filter(fn (ScheduledVisit $visit): bool => $this->isUrgent($visit, $now))
            ->sortBy(fn (ScheduledVisit $visit): int => $this->scheduledStart($visit)?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Notify Admins once for each unresolved visit that newly crossed the urgency threshold.
     *
     * @return int Number of visits escalated by this run.
     */
    public function escalateDueUrgency(?CarbonInterface $now = null): int
    {
        $now = $this->settings->now($now);
        $count = 0;

        $this->candidateQuery(ScheduledVisit::query(), $now)
            ->whereNull('staffing_urgency_escalated_at')
            ->get()
            ->each(function (ScheduledVisit $visit) use ($now, &$count): void {
                $state = $this->stateFor($visit, $now);

                if ($state !== self::STATE_URGENT && $state !== self::STATE_OVERDUE) {
                    return;
                }

                // Atomic claim: only one dispatcher may escalate a given episode.
                $claimed = ScheduledVisit::query()
                    ->whereKey($visit->id)
                    ->whereNull('staffing_urgency_escalated_at')
                    ->update(['staffing_urgency_escalated_at' => $now->timezone((string) config('app.timezone', 'UTC'))]);

                if ($claimed !== 1) {
                    return;
                }

                $visit->refresh();
                $this->notifications->staffingNeededUrgencyReached(
                    $visit,
                    $state === self::STATE_OVERDUE,
                    $this->thresholdHours(),
                );
                $count++;
            });

        return $count;
    }

    /**
     * Clear the one-time escalation marker (visit staffed or released into a new episode).
     */
    public function clearEscalation(ScheduledVisit $visit): void
    {
        if ($visit->staffing_urgency_escalated_at === null) {
            return;
        }

        ScheduledVisit::query()
            ->whereKey($visit->id)
            ->update(['staffing_urgency_escalated_at' => null]);

        $visit->staffing_urgency_escalated_at = null;
        $visit->syncOriginalAttribute('staffing_urgency_escalated_at');
    }
}
