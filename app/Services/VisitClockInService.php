<?php

namespace App\Services;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitClockInService
{
    public function __construct(
        private VisitTaskGenerator $tasks,
        private VisitExceptionService $exceptions,
        private SettingsService $settings,
        private VisitSafetyCheckService $safetyChecks,
        private CarePlanRevisionService $carePlanRevisions,
        private ComplianceEligibilityService $complianceEligibility,
        private ComplianceStaffingService $complianceStaffing,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function clockIn(User $user, ScheduledVisit $scheduledVisit, array $payload): Visit
    {
        return DB::transaction(function () use ($user, $scheduledVisit, $payload): Visit {
            /** @var ScheduledVisit $scheduledVisit */
            $scheduledVisit = ScheduledVisit::query()
                ->whereKey($scheduledVisit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = $user->employee;

            if ($employee === null || ! $employee->isActiveDsp()) {
                abort(403);
            }

            if ($scheduledVisit->employee_id !== $employee->id) {
                abort(403);
            }

            $activeForSchedule = Visit::query()
                ->where('scheduled_visit_id', $scheduledVisit->id)
                ->inProgress()
                ->lockForUpdate()
                ->first();

            if ($activeForSchedule !== null) {
                if ($activeForSchedule->employee_id === $employee->id) {
                    $this->tasks->generate($activeForSchedule);

                    return $activeForSchedule;
                }

                throw ValidationException::withMessages([
                    'scheduled_visit' => 'Another DSP still has an active visit segment for this scheduled visit.',
                ]);
            }

            // Cancelled is never startable — handoff_pending does not bypass lifecycle cancel.
            if ($scheduledVisit->status === ScheduledVisitStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'This scheduled visit cannot be started.',
                ]);
            }

            if ($scheduledVisit->status === ScheduledVisitStatus::Completed
                && ! $scheduledVisit->handoff_pending) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'This scheduled visit cannot be started.',
                ]);
            }

            $priorCompleted = Visit::query()
                ->where('scheduled_visit_id', $scheduledVisit->id)
                ->where('status', VisitStatus::Completed)
                ->orderByDesc('id')
                ->first();

            // Mid-service handoff: replacement DSP clocks in after prior segment ended.
            // Bypass is limited to the "already has a completed segment" gate — not compliance,
            // assignee identity, one-active-per-DSP, or Cancelled status.
            if ($priorCompleted !== null && ! $scheduledVisit->handoff_pending) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'This scheduled visit already has a completed visit. Use the authorized handoff workflow before starting another segment.',
                ]);
            }

            if ($priorCompleted !== null && $scheduledVisit->handoff_pending
                && $scheduledVisit->employee_id !== $employee->id) {
                abort(403);
            }

            $now = $this->settings->now();

            $complianceBlock = $this->complianceEligibility->serviceBlockReason($employee, $now);
            if ($complianceBlock !== null && ! $this->complianceStaffing->allowsGraceClockIn($scheduledVisit)) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => $complianceBlock.' Start Visit is blocked until the Hard Stop is resolved or an authorized temporary exception is active.',
                ]);
            }

            // Handoff replacements are InProgress + handoff_pending; only Scheduled rows
            // require the normal early/late window (isEligibleToStart).
            if ($scheduledVisit->status === ScheduledVisitStatus::Scheduled && ! $scheduledVisit->isEligibleToStart($now)) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => $this->ineligibleMessage($scheduledVisit, $now),
                ]);
            }

            if ($scheduledVisit->isEarlyStart($now) && ! filter_var($payload['early_start_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'early_start_confirmed' => 'Confirm that you still want to start this shift early.',
                ]);
            }

            $active = Visit::query()
                ->where('employee_id', $employee->id)
                ->inProgress()
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'You already have an active visit. Continue that visit before starting another.',
                ]);
            }

            $attributes = $this->visitAttributes($scheduledVisit, $employee, $payload, $now);
            if ($priorCompleted !== null) {
                $attributes['replaced_visit_id'] = $priorCompleted->id;
            }

            try {
                $visit = Visit::query()->create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                $existing = Visit::query()
                    ->where('scheduled_visit_id', $scheduledVisit->id)
                    ->inProgress()
                    ->first();

                if ($existing !== null && $existing->employee_id === $employee->id) {
                    $this->tasks->generate($existing);

                    return $existing;
                }

                throw ValidationException::withMessages([
                    'scheduled_visit' => 'You already have an active visit. Continue that visit before starting another.',
                ]);
            }

            $scheduledVisit->update([
                'status' => ScheduledVisitStatus::InProgress,
                'handoff_pending' => false,
            ]);

            if ($scheduledVisit->isComplianceGrace()) {
                $this->complianceStaffing->markGraceConsumed($scheduledVisit->fresh() ?? $scheduledVisit, $user);
            }

            if ($priorCompleted !== null) {
                $this->tasks->seedUnresolvedFromPrior($priorCompleted, $visit);
                $this->recordHandoffGapIfNeeded($visit, $priorCompleted, $now);
            } else {
                $this->tasks->generate($visit);
            }

            $this->recordStartExceptions($visit, $scheduledVisit, $payload, $now);

            return $visit;
        });
    }

    private function recordHandoffGapIfNeeded(
        Visit $incoming,
        Visit $prior,
        CarbonInterface $now,
    ): void {
        $priorEnd = $prior->effectiveEndedAt();
        if ($priorEnd === null || $incoming->clocked_in_at === null) {
            return;
        }

        $gapMinutes = (int) $priorEnd->diffInMinutes($incoming->clocked_in_at, false);
        if ($gapMinutes <= 0) {
            return;
        }

        $this->exceptions->record(
            $incoming,
            VisitExceptionType::HandoffCoverageGap,
            'Unserved coverage gap between linked handoff Visit segments.',
            null,
            [
                'prior_visit_id' => $prior->id,
                'gap_minutes' => $gapMinutes,
                'prior_effective_end' => $priorEnd->toIso8601String(),
                'replacement_clock_in' => $incoming->clocked_in_at->toIso8601String(),
                'recorded_at' => $now->toIso8601String(),
            ],
        );
    }

    public function activeVisitFor(Employee $employee): ?Visit
    {
        return Visit::query()
            ->with(['client', 'scheduledVisit.shiftTemplate', 'tasks'])
            ->where('employee_id', $employee->id)
            ->inProgress()
            ->orderByDesc('clocked_in_at')
            ->orderByDesc('id')
            ->first();
    }

    public function eligibleScheduledVisitFor(Employee $employee): ?ScheduledVisit
    {
        return $this->todayScheduledVisits($employee)
            ->first(fn (ScheduledVisit $visit): bool => $this->isClockInEligible($visit));
    }

    public function primaryScheduledVisitFor(Employee $employee): ?ScheduledVisit
    {
        $todayVisits = $this->todayScheduledVisits($employee);

        if ($todayVisits->isEmpty()) {
            return null;
        }

        return $todayVisits->first(fn (ScheduledVisit $visit): bool => $this->isClockInEligible($visit))
            ?? $todayVisits->first();
    }

    private function isClockInEligible(ScheduledVisit $visit): bool
    {
        return $visit->handoff_pending || $visit->isEligibleToStart();
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    public function laterTodayScheduledVisits(Employee $employee, ?ScheduledVisit $primary = null): Collection
    {
        $primaryId = $primary?->id;

        return $this->todayScheduledVisits($employee)
            ->reject(fn (ScheduledVisit $visit): bool => $visit->id === $primaryId)
            ->values();
    }

    public function nextFutureScheduledVisit(Employee $employee): ?ScheduledVisit
    {
        $today = $this->settings->today();

        return ScheduledVisit::query()
            ->with(['client', 'employee', 'supervisor', 'shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->scheduled()
            ->whereDate('service_date', '>', $today)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ScheduledVisit $visit): int => $visit->startsAtOn()->getTimestamp())
            ->first();
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function todayScheduledVisits(Employee $employee): Collection
    {
        $today = $this->settings->today();

        return ScheduledVisit::query()
            ->with(['client', 'employee', 'supervisor', 'shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->whereDate('service_date', $today)
            ->where(function ($query): void {
                $query->where('status', ScheduledVisitStatus::Scheduled)
                    ->orWhere(function ($handoff): void {
                        $handoff->where('handoff_pending', true)
                            ->where('status', ScheduledVisitStatus::InProgress);
                    });
            })
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ScheduledVisit $visit): int => $visit->startsAtOn()->getTimestamp())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function visitAttributes(
        ScheduledVisit $scheduledVisit,
        Employee $employee,
        array $payload,
        CarbonInterface $now,
    ): array {
        $status = ClockInLocationStatus::from((string) $payload['location_status']);
        $captured = $status === ClockInLocationStatus::Captured;
        $unavailableReason = $captured ? null : GpsFallbackReason::storedReasonFrom($payload);

        $carePlan = $this->carePlanRevisions->effectivePlanFor(
            $scheduledVisit->client_id,
            $scheduledVisit->service_date,
        );

        // Temporary Visit used only to resolve the standing Care Plan snapshot.
        $probe = new Visit([
            'client_id' => $scheduledVisit->client_id,
            'scheduled_visit_id' => $scheduledVisit->id,
            'care_plan_id' => $carePlan?->id,
        ]);
        $probe->setRelation('scheduledVisit', $scheduledVisit);
        if ($carePlan !== null) {
            $probe->setRelation('carePlan', $carePlan);
        }
        $supervision = $this->safetyChecks->snapshotForVisit($probe);

        return [
            'scheduled_visit_id' => $scheduledVisit->id,
            'employee_id' => $employee->id,
            'client_id' => $scheduledVisit->client_id,
            'care_plan_id' => $carePlan?->id,
            'service_type' => $scheduledVisit->service_type,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => $now,
            'clock_in_latitude' => $captured ? $payload['latitude'] : null,
            'clock_in_longitude' => $captured ? $payload['longitude'] : null,
            'clock_in_accuracy' => $captured ? ($payload['accuracy'] ?? null) : null,
            'clock_in_location_method' => ClockInLocationMethod::from((string) $payload['location_method']),
            'clock_in_location_status' => $status,
            'clock_in_unavailable_reason' => $unavailableReason,
            'safety_check_interval_minutes' => $supervision['safety_check_interval_minutes'],
            'supervision_instruction' => $supervision['supervision_instruction'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordStartExceptions(
        Visit $visit,
        ScheduledVisit $scheduledVisit,
        array $payload,
        CarbonInterface $now,
    ): void {
        $status = ClockInLocationStatus::from((string) $payload['location_status']);

        if ($status !== ClockInLocationStatus::Captured) {
            $reasonCode = isset($payload['gps_fallback_reason'])
                ? GpsFallbackReason::tryFrom((string) $payload['gps_fallback_reason'])
                : null;

            $this->exceptions->record(
                $visit,
                VisitExceptionType::GpsUnavailable,
                'Clock-in GPS was not captured.',
                null,
                [
                    'location_status' => $status->value,
                    'reason' => GpsFallbackReason::storedReasonFrom($payload),
                    'reason_code' => $reasonCode?->value,
                    'reason_note' => $payload['gps_fallback_note'] ?? null,
                ],
            );
        }

        if ($scheduledVisit->isLateRelativeToStart($now)) {
            $this->exceptions->record(
                $visit,
                VisitExceptionType::LateStart,
                'Visit started after the scheduled start time.',
                null,
                [
                    'scheduled_start' => $scheduledVisit->startsAtOn()->toIso8601String(),
                    'clocked_in_at' => $now->toIso8601String(),
                ],
            );
        }
    }

    private function ineligibleMessage(ScheduledVisit $scheduledVisit, CarbonInterface $now): string
    {
        try {
            $start = $scheduledVisit->startsAtOn();
            $end = $scheduledVisit->endsAtOn();
            $earliest = $start->copy()->subMinutes($scheduledVisit->earlyStartMinutes());
        } catch (\RuntimeException) {
            return 'This scheduled visit is not eligible to start now.';
        }

        if ($now->lt($earliest)) {
            return 'This visit can be started up to '.$scheduledVisit->earlyStartMinutes().' minutes before the scheduled start.';
        }

        if ($now->gt($end)) {
            return 'This scheduled visit window has ended and cannot be started.';
        }

        return 'This scheduled visit is not eligible to start now.';
    }
}
