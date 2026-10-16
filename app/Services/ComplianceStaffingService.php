<?php

namespace App\Services;

use App\Enums\ComplianceStaffingStatus;
use App\Enums\EmployeeAdminHistoryCategory;
use App\Enums\ScheduledVisitStatus;
use App\Models\ComplianceStaffingEpisode;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MDM 04-18 — Newly Effective Hard-Stop Compliance / Qualification Issue.
 *
 * Preserves the nearest upcoming already-scheduled visit as a Grace Visit,
 * marks later assigned visits At Risk / Action Required, notifies Supervisor
 * (with Admin visibility), and clears markings when eligibility is restored.
 * Does not invent Admin "grant grace" workflows or Supervisor Hard-Stop overrides.
 * Does not apply when MDM 08 lifecycle independently blocks service.
 */
class ComplianceStaffingService
{
    public function __construct(
        private ComplianceEligibilityService $eligibility,
        private SettingsService $settings,
        private SchedulingNotificationService $notifications,
        private EmployeeAdminHistoryService $history,
    ) {}

    /**
     * @return array{
     *     applied: bool,
     *     cleared: bool,
     *     skipped_lifecycle: bool,
     *     grace_visit_id: int|null,
     *     at_risk_count: int,
     *     episode_id: int|null
     * }
     */
    public function syncForEmployee(Employee $employee, ?User $actor = null, bool $notify = true): array
    {
        return DB::transaction(function () use ($employee, $actor, $notify): array {
            $employee->refresh();

            if (! $employee->canPerformService()) {
                $cleared = $this->clearComplianceMarks($employee, $actor, 'lifecycle_ineligible');

                return [
                    'applied' => false,
                    'cleared' => $cleared,
                    'skipped_lifecycle' => true,
                    'grace_visit_id' => null,
                    'at_risk_count' => 0,
                    'episode_id' => null,
                ];
            }

            $evaluation = $this->eligibility->evaluate($employee);

            if (! $evaluation['hard_stop']) {
                $cleared = $this->clearComplianceMarks($employee, $actor, 'compliance_restored');

                return [
                    'applied' => false,
                    'cleared' => $cleared,
                    'skipped_lifecycle' => false,
                    'grace_visit_id' => null,
                    'at_risk_count' => 0,
                    'episode_id' => null,
                ];
            }

            $summary = $evaluation['block_reason'] ?? $evaluation['summary'];
            $visits = $this->upcomingAssignedVisits($employee);

            /** @var ComplianceStaffingEpisode|null $episode */
            $episode = ComplianceStaffingEpisode::query()
                ->where('employee_id', $employee->id)
                ->open()
                ->lockForUpdate()
                ->first();

            $isNewEpisode = $episode === null;
            $graceVisit = null;
            $graceConsumed = $episode?->graceConsumed() ?? false;

            if ($episode !== null && $episode->grace_scheduled_visit_id !== null && ! $graceConsumed) {
                $graceVisit = $visits->first(
                    fn (ScheduledVisit $visit): bool => $visit->id === $episode->grace_scheduled_visit_id,
                );

                if ($graceVisit === null) {
                    // Grace visit no longer upcoming (started, completed, cancelled, or past).
                    $graceConsumed = true;
                    $episode->forceFill([
                        'grace_consumed_at' => $episode->grace_consumed_at ?? $this->settings->now(),
                    ])->save();
                }
            }

            if (! $graceConsumed && $graceVisit === null && $visits->isNotEmpty()) {
                $graceVisit = $visits->first();
            }

            $atRisk = $visits
                ->reject(fn (ScheduledVisit $visit): bool => $graceVisit !== null && $visit->id === $graceVisit->id)
                ->values();

            $now = $this->settings->now();

            if ($episode === null) {
                $episode = ComplianceStaffingEpisode::query()->create([
                    'employee_id' => $employee->id,
                    'grace_scheduled_visit_id' => $graceVisit?->id,
                    'hard_stop_summary' => $summary,
                    'triggered_at' => $now,
                    'triggered_by_user_id' => $actor?->id,
                ]);
            } else {
                $episode->forceFill([
                    'grace_scheduled_visit_id' => $graceConsumed
                        ? $episode->grace_scheduled_visit_id
                        : ($graceVisit?->id ?? $episode->grace_scheduled_visit_id),
                    'hard_stop_summary' => $summary,
                ])->save();
            }

            if ($graceVisit !== null && ! $graceConsumed) {
                $this->markVisit(
                    $graceVisit,
                    ComplianceStaffingStatus::Grace,
                    $summary,
                    $now,
                );
            }

            foreach ($atRisk as $visit) {
                $this->markVisit(
                    $visit,
                    ComplianceStaffingStatus::AtRisk,
                    $summary,
                    $now,
                );
            }

            // Clear marks on visits that are no longer in the upcoming set (e.g. cancelled).
            $keepIds = $visits->pluck('id')->all();
            ScheduledVisit::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('compliance_staffing_status')
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
                ->when($keepIds === [], fn ($q) => $q)
                ->get()
                ->each(fn (ScheduledVisit $visit) => $this->clearVisitMark($visit));

            if ($isNewEpisode) {
                $this->history->record($employee, $actor, [
                    'category' => EmployeeAdminHistoryCategory::ComplianceStaffing,
                    'summary' => 'Hard Stop compliance staffing episode opened (MDM 04-18).',
                    'new_value' => ComplianceStaffingStatus::Grace->value,
                    'reason' => $summary,
                    'subject' => $episode,
                    'meta' => [
                        'grace_scheduled_visit_id' => $graceVisit?->id,
                        'at_risk_count' => $atRisk->count(),
                    ],
                ]);

                if ($notify) {
                    $this->notifications->complianceHardStopStaffing(
                        $employee,
                        $graceVisit,
                        $atRisk->count(),
                        $summary,
                    );
                }
            }

            return [
                'applied' => true,
                'cleared' => false,
                'skipped_lifecycle' => false,
                'grace_visit_id' => $graceConsumed ? null : $graceVisit?->id,
                'at_risk_count' => $atRisk->count(),
                'episode_id' => $episode->id,
            ];
        });
    }

    public function markGraceConsumed(ScheduledVisit $visit, ?User $actor = null): void
    {
        if ($visit->complianceStaffingStatus() !== ComplianceStaffingStatus::Grace) {
            return;
        }

        $employeeId = $visit->employee_id;
        if ($employeeId === null) {
            return;
        }

        /** @var ComplianceStaffingEpisode|null $episode */
        $episode = ComplianceStaffingEpisode::query()
            ->where('employee_id', $employeeId)
            ->open()
            ->where('grace_scheduled_visit_id', $visit->id)
            ->first();

        if ($episode !== null && $episode->grace_consumed_at === null) {
            $episode->forceFill([
                'grace_consumed_at' => $this->settings->now(),
            ])->save();
        }

        $employee = Employee::query()->find($employeeId);
        if ($employee !== null) {
            $this->syncForEmployee($employee, $actor, notify: false);
        }
    }

    public function syncEmployeesWithHardStops(?User $actor = null): int
    {
        $count = 0;

        Employee::query()
            ->where('job_type', \App\Enums\JobType::Dsp)
            ->operational()
            ->orderBy('id')
            ->each(function (Employee $employee) use ($actor, &$count): void {
                if ($this->eligibility->evaluate($employee)['hard_stop']) {
                    $this->syncForEmployee($employee, $actor);
                    $count++;
                } else {
                    $this->syncForEmployee($employee, $actor, notify: false);
                }
            });

        return $count;
    }

    public function allowsGraceClockIn(ScheduledVisit $visit): bool
    {
        return $visit->complianceStaffingStatus() === ComplianceStaffingStatus::Grace;
    }

    private function clearComplianceMarks(Employee $employee, ?User $actor, string $reason): bool
    {
        $marked = ScheduledVisit::query()
            ->where(function ($query) use ($employee): void {
                $query->where('employee_id', $employee->id)
                    ->orWhere(function ($inner) use ($employee): void {
                        $inner->whereNull('employee_id')
                            ->where('released_employee_id', $employee->id)
                            ->whereNotNull('compliance_staffing_status');
                    });
            })
            ->whereNotNull('compliance_staffing_status')
            ->get();

        foreach ($marked as $visit) {
            $this->clearVisitMark($visit);
        }

        /** @var ComplianceStaffingEpisode|null $episode */
        $episode = ComplianceStaffingEpisode::query()
            ->where('employee_id', $employee->id)
            ->open()
            ->first();

        if ($episode !== null) {
            $episode->forceFill([
                'resolved_at' => $this->settings->now(),
                'resolved_reason' => $reason,
            ])->save();

            $this->history->record($employee, $actor, [
                'category' => EmployeeAdminHistoryCategory::ComplianceStaffing,
                'summary' => 'Hard Stop compliance staffing episode resolved.',
                'old_value' => ComplianceStaffingStatus::AtRisk->value,
                'new_value' => ComplianceStaffingStatus::None->value,
                'reason' => $reason,
                'subject' => $episode,
            ]);

            return true;
        }

        return $marked->isNotEmpty();
    }

    private function markVisit(
        ScheduledVisit $visit,
        ComplianceStaffingStatus $status,
        string $summary,
        \Carbon\CarbonInterface $now,
    ): void {
        $attention = $status === ComplianceStaffingStatus::AtRisk;
        $reason = $status === ComplianceStaffingStatus::Grace
            ? 'Grace Visit: nearest upcoming assignment preserved under Hard Stop (MDM 04-18). '.$summary
            : 'At Risk / Action Required: Hard Stop compliance — Supervisor resolution needed (MDM 04-18). '.$summary;

        $visit->forceFill([
            'compliance_staffing_status' => $status->value,
            'compliance_staffing_marked_at' => $now,
            'compliance_staffing_reason' => $reason,
            'needs_attention' => $attention || $visit->needs_attention || $visit->staffing_needed,
            'attention_reason' => $attention
                ? $reason
                : ($visit->attention_reason ?? $reason),
        ])->save();
    }

    private function clearVisitMark(ScheduledVisit $visit): void
    {
        $wasComplianceAttention = is_string($visit->attention_reason)
            && str_contains($visit->attention_reason, 'MDM 04-18');

        $visit->forceFill([
            'compliance_staffing_status' => null,
            'compliance_staffing_marked_at' => null,
            'compliance_staffing_reason' => null,
            'needs_attention' => $visit->staffing_needed ? true : ($wasComplianceAttention ? false : $visit->needs_attention),
            'attention_reason' => $wasComplianceAttention
                ? ($visit->staffing_needed ? 'Staffing Needed' : null)
                : $visit->attention_reason,
        ])->save();
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function upcomingAssignedVisits(Employee $employee): Collection
    {
        $today = $this->settings->today();

        return ScheduledVisit::query()
            ->with(['client', 'supervisor', 'employee'])
            ->where('employee_id', $employee->id)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $today)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ScheduledVisit $visit): int => $visit->startsAtOn()->getTimestamp())
            ->values();
    }
}
