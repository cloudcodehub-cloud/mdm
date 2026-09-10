<?php

namespace App\Services;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
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
    public function __construct(private VisitTaskGenerator $tasks) {}

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

            if ($scheduledVisit->status === ScheduledVisitStatus::Scheduled && ! $scheduledVisit->isEligibleToStart()) {
                throw ValidationException::withMessages([
                    'scheduled_visit' => 'This scheduled visit is not eligible to start now.',
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
                $visit = Visit::query()->create($this->visitAttributes($scheduledVisit, $employee, $payload));
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

            return $visit;
        });
    }

    public function activeVisitFor(Employee $employee): ?Visit
    {
        return Visit::query()
            ->with(['client', 'scheduledVisit.shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->inProgress()
            ->first();
    }

    public function eligibleScheduledVisitFor(Employee $employee): ?ScheduledVisit
    {
        return ScheduledVisit::query()
            ->with(['client', 'employee', 'shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->scheduled()
            ->orderBy('service_date')
            ->orderBy('id')
            ->get()
            ->first(fn (ScheduledVisit $visit): bool => $visit->isEligibleToStart());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function visitAttributes(ScheduledVisit $scheduledVisit, Employee $employee, array $payload): array
    {
        $status = ClockInLocationStatus::from((string) $payload['location_status']);
        $captured = $status === ClockInLocationStatus::Captured;

        return [
            'scheduled_visit_id' => $scheduledVisit->id,
            'employee_id' => $employee->id,
            'client_id' => $scheduledVisit->client_id,
            'service_type' => $scheduledVisit->service_type,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now(),
            'clock_in_latitude' => $captured ? $payload['latitude'] : null,
            'clock_in_longitude' => $captured ? $payload['longitude'] : null,
            'clock_in_accuracy' => $captured ? ($payload['accuracy'] ?? null) : null,
            'clock_in_location_method' => ClockInLocationMethod::from((string) $payload['location_method']),
            'clock_in_location_status' => $status,
            'clock_in_unavailable_reason' => $captured ? null : $payload['unavailable_reason'],
        ];
    }
}
