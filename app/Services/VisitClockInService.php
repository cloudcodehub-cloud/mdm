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
use Illuminate\Database\UniqueConstraintViolationException;
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

            $existing = Visit::query()
                ->where('scheduled_visit_id', $scheduledVisit->id)
                ->first();

            if ($existing !== null) {
                $this->tasks->generate($existing);

                return $existing;
            }

            if (in_array($scheduledVisit->status, [
                ScheduledVisitStatus::Cancelled,
                ScheduledVisitStatus::Completed,
            ], true)) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'This scheduled visit cannot be started.',
                ]);
            }

            $now = $this->settings->now();

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

            try {
                $visit = Visit::query()->create($this->visitAttributes($scheduledVisit, $employee, $payload, $now));
            } catch (UniqueConstraintViolationException $exception) {
                $existing = Visit::query()
                    ->where('scheduled_visit_id', $scheduledVisit->id)
                    ->first();

                if ($existing !== null) {
                    $this->tasks->generate($existing);

                    return $existing;
                }

                throw ValidationException::withMessages([
                    'scheduled_visit' => 'You already have an active visit. Continue that visit before starting another.',
                ]);
            }

            $scheduledVisit->update([
                'status' => ScheduledVisitStatus::InProgress,
            ]);

            $this->tasks->generate($visit);
            $this->recordStartExceptions($visit, $scheduledVisit, $payload, $now);

            return $visit;
        });
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
            ->first(fn (ScheduledVisit $visit): bool => $visit->isEligibleToStart());
    }

    public function primaryScheduledVisitFor(Employee $employee): ?ScheduledVisit
    {
        $todayVisits = $this->todayScheduledVisits($employee);

        if ($todayVisits->isEmpty()) {
            return null;
        }

        return $todayVisits->first(fn (ScheduledVisit $visit): bool => $visit->isEligibleToStart())
            ?? $todayVisits->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ScheduledVisit>
     */
    public function laterTodayScheduledVisits(Employee $employee, ?ScheduledVisit $primary = null): \Illuminate\Support\Collection
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
     * @return \Illuminate\Support\Collection<int, ScheduledVisit>
     */
    private function todayScheduledVisits(Employee $employee): \Illuminate\Support\Collection
    {
        $today = $this->settings->today();

        return ScheduledVisit::query()
            ->with(['client', 'employee', 'supervisor', 'shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->scheduled()
            ->whereDate('service_date', $today)
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
        \Carbon\CarbonInterface $now,
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
        \Carbon\CarbonInterface $now,
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

    private function ineligibleMessage(ScheduledVisit $scheduledVisit, \Carbon\CarbonInterface $now): string
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
