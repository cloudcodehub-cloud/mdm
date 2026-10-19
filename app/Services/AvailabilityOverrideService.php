<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAvailabilityOverride;
use App\Models\User;
use App\Support\ClockMinutes;
use App\Support\DirectoryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * MDM 04-19: assigning a DSP to a Visit that conflicts with an approved unavailable
 * period requires an authorized, reasoned, Visit-specific override. The DSP has no veto
 * but is prominently notified and must acknowledge; unacknowledged near-term overrides
 * surface to the responsible Supervisor.
 */
class AvailabilityOverrideService
{
    public const NEAR_TERM_DAYS = 7;

    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * Approved-Unavailable conflict for the visit's currently assigned DSP.
     *
     * @return array{kind: string, message: string}|null
     */
    public function conflictForVisit(ScheduledVisit $visit): ?array
    {
        if ($visit->employee_id === null) {
            return null;
        }

        $visit->loadMissing('shiftTemplate');
        $dsp = Employee::query()->find($visit->employee_id);

        if ($dsp === null) {
            return null;
        }

        $template = $visit->shiftTemplate;
        $window = ClockMinutes::window(
            $visit->starts_at ?? ($template !== null ? (string) $template->starts_at : '00:00:00'),
            $visit->ends_at ?? ($template !== null ? (string) $template->ends_at : '23:59:00'),
        );

        return app(DspAvailabilityService::class)->availabilityConflict($dsp, $visit->service_date, $window);
    }

    /**
     * Whether the user may authorize an unavailable-period override for this assignment.
     * Mirrors scheduled-visit create/replace authority; DSPs never authorize.
     */
    public function userMayOverride(User $user, ScheduledVisit $visit): bool
    {
        if ($user->isDsp()) {
            return false;
        }

        return $user->isAdmin() || ($user->isSupervisor() && $user->can('update', $visit));
    }

    /**
     * Record the override (audit row + DSP notification) when the visit's assigned DSP
     * is in an approved unavailable conflict and a reason was supplied.
     */
    public function recordIfConflict(ScheduledVisit $visit, User $actor, ?string $reason): ?ScheduledVisitAvailabilityOverride
    {
        $reason = is_string($reason) ? trim($reason) : '';

        if ($reason === '' || $visit->employee_id === null || $visit->status !== ScheduledVisitStatus::Scheduled) {
            return null;
        }

        if (! $this->userMayOverride($actor, $visit)) {
            return null;
        }

        $conflict = $this->conflictForVisit($visit);

        if ($conflict === null) {
            return null;
        }

        $override = ScheduledVisitAvailabilityOverride::query()->create([
            'scheduled_visit_id' => $visit->id,
            'employee_id' => $visit->employee_id,
            'conflict_kind' => $conflict['kind'],
            'conflict_summary' => $conflict['message'],
            'override_reason' => $reason,
            'overridden_by_user_id' => $actor->id,
            'overridden_at' => now(),
        ]);

        $this->notifyDsp($visit, $override, $actor);

        return $override;
    }

    public function hasOverrideFor(ScheduledVisit $visit, int $employeeId): bool
    {
        return ScheduledVisitAvailabilityOverride::query()
            ->where('scheduled_visit_id', $visit->id)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    /**
     * DSP acknowledges every outstanding override on their own assignment of the visit.
     */
    public function acknowledge(ScheduledVisit $visit, User $user): int
    {
        $count = ScheduledVisitAvailabilityOverride::query()
            ->where('scheduled_visit_id', $visit->id)
            ->where('employee_id', $visit->employee_id)
            ->unacknowledged()
            ->update([
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => $user->id,
                'updated_at' => now(),
            ]);

        if ($count > 0) {
            InAppNotification::query()
                ->where('user_id', $user->id)
                ->where('source_key', 'like', 'schedule.visit.'.$visit->id.'.availability-override.%')
                ->unread()
                ->update(['read_at' => now()]);
        }

        return $count;
    }

    public function hasUnacknowledged(ScheduledVisit $visit): bool
    {
        return ScheduledVisitAvailabilityOverride::query()
            ->where('scheduled_visit_id', $visit->id)
            ->where('employee_id', $visit->employee_id)
            ->unacknowledged()
            ->exists();
    }

    /**
     * Unacknowledged overrides on still-assigned, near-term (today through +7 days) visits
     * within the given (already caseload-scoped) scheduled-visit query.
     *
     * @param  Builder<ScheduledVisit>  $scheduledQuery
     * @return Collection<int, ScheduledVisitAvailabilityOverride>
     */
    public function unacknowledgedNearTerm(Builder $scheduledQuery, int $limit = 25): Collection
    {
        $today = $this->settings->today();
        $until = Carbon::parse($today)->addDays(self::NEAR_TERM_DAYS)->toDateString();

        $rows = ScheduledVisitAvailabilityOverride::query()
            ->unacknowledged()
            ->whereHas('scheduledVisit', function (Builder $query) use ($scheduledQuery, $today, $until): void {
                $query->whereIn('scheduled_visits.id', (clone $scheduledQuery)->select('scheduled_visits.id'))
                    ->whereColumn('scheduled_visits.employee_id', 'scheduled_visit_availability_overrides.employee_id')
                    ->where('scheduled_visits.status', ScheduledVisitStatus::Scheduled)
                    ->whereDate('scheduled_visits.service_date', '>=', $today)
                    ->whereDate('scheduled_visits.service_date', '<=', $until);
            })
            ->with(['scheduledVisit.client', 'scheduledVisit.shiftTemplate', 'employee', 'overriddenBy'])
            ->orderBy('overridden_at')
            ->orderBy('id')
            ->get();

        return $rows
            ->unique('scheduled_visit_id')
            ->sortBy(fn (ScheduledVisitAvailabilityOverride $row): string => $row->scheduledVisit->service_date->toDateString().'-'.str_pad((string) $row->id, 10, '0', STR_PAD_LEFT))
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeAttention(ScheduledVisitAvailabilityOverride $override): array
    {
        $visit = $override->scheduledVisit;
        $today = Carbon::parse($this->settings->today())->startOfDay();
        $days = (int) $today->diffInDays($visit->service_date->copy()->startOfDay(), false);

        return [
            'id' => $override->id,
            'scheduled_visit_id' => $visit->id,
            'service_date' => $visit->service_date->toDateString(),
            'time_label' => DirectoryPresenter::visitTimeLabel($visit),
            'days_until' => $days,
            'client' => [
                'id' => $visit->client->id,
                'name' => $visit->client->full_name,
            ],
            'employee' => [
                'id' => $override->employee->id,
                'name' => $override->employee->full_name,
            ],
            'conflict_kind' => $override->conflict_kind,
            'conflict_summary' => $override->conflict_summary,
            'override_reason' => $override->override_reason,
            'overridden_by_name' => $override->overriddenBy?->name,
            'overridden_at_label' => $this->settings->formatDateTime($override->overridden_at),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForVisit(ScheduledVisit $visit): array
    {
        $visit->loadMissing(['availabilityOverrides.overriddenBy', 'availabilityOverrides.acknowledgedBy', 'availabilityOverrides.employee']);

        $rows = [];

        foreach ($visit->availabilityOverrides as $override) {
            $rows[] = [
                'id' => $override->id,
                'employee_id' => $override->employee_id,
                'employee_name' => $override->employee?->full_name,
                'current_assignment' => $override->employee_id === $visit->employee_id,
                'conflict_kind' => $override->conflict_kind,
                'conflict_summary' => $override->conflict_summary,
                'override_reason' => $override->override_reason,
                'overridden_by_name' => $override->overriddenBy?->name,
                'overridden_at_label' => $this->settings->formatDateTime($override->overridden_at),
                'acknowledged' => $override->isAcknowledged(),
                'acknowledged_at_label' => $override->acknowledged_at === null
                    ? null
                    : $this->settings->formatDateTime($override->acknowledged_at),
                'acknowledged_by_name' => $override->acknowledgedBy?->name,
            ];
        }

        return $rows;
    }

    private function notifyDsp(ScheduledVisit $visit, ScheduledVisitAvailabilityOverride $override, User $actor): void
    {
        $visit->loadMissing(['client', 'shiftTemplate']);
        $dspUser = Employee::query()->with('user')->find($override->employee_id)?->user;

        if (! $dspUser instanceof User) {
            return;
        }

        $kind = $override->conflict_kind === ScheduledVisitAvailabilityOverride::KIND_LEAVE
            ? 'approved leave'
            : 'approved unavailable period';

        $this->notifications->notify(
            $dspUser,
            InAppNotificationType::AvailabilityOverride,
            'Action required: assigned during your '.$kind,
            $visit->client->full_name.' · '.$visit->service_date->toDateString().' · '.DirectoryPresenter::visitTimeLabel($visit)
                .'. A supervisor assigned you to this visit despite your '.$kind.'. Reason: '.$override->override_reason
                .' Open the visit and acknowledge this assignment.',
            'schedule.visit.'.$visit->id.'.availability-override.'.$override->id,
            route('scheduled-visits.show', $visit),
        );
    }
}
