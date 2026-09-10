<?php

namespace App\Services;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitClockOutService
{
    public function __construct(private VisitExceptionService $exceptions) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateNotes(Visit $visit, array $payload): Visit
    {
        return DB::transaction(function () use ($visit, $payload): Visit {
            /** @var Visit $visit */
            $visit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $this->assertInProgress($visit);

            $visit->update([
                'visit_notes' => $payload['visit_notes'] ?? null,
                'handover_note' => $payload['handover_note'] ?? null,
            ]);

            return $visit->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function clockOut(User $user, Visit $visit, array $payload): Visit
    {
        return DB::transaction(function () use ($user, $visit, $payload): Visit {
            /** @var Visit $visit */
            $visit = Visit::query()
                ->with('tasks')
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = $user->employee;

            if ($employee === null || ! $employee->isActiveDsp() || $visit->employee_id !== $employee->id) {
                abort(403);
            }

            if ($visit->status === VisitStatus::Completed || $visit->clocked_out_at !== null) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit has already been clocked out.',
                ]);
            }

            $this->assertInProgress($visit);

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

            $status = ClockInLocationStatus::from((string) $payload['location_status']);
            $captured = $status === ClockInLocationStatus::Captured;

            $visit->update([
                'status' => VisitStatus::Completed,
                'clocked_out_at' => now(),
                'clock_out_latitude' => $captured ? $payload['latitude'] : null,
                'clock_out_longitude' => $captured ? $payload['longitude'] : null,
                'clock_out_accuracy' => $captured ? ($payload['accuracy'] ?? null) : null,
                'clock_out_location_method' => ClockInLocationMethod::from((string) $payload['location_method']),
                'clock_out_location_status' => $status,
                'clock_out_unavailable_reason' => $captured ? null : $payload['unavailable_reason'],
                'visit_notes' => array_key_exists('visit_notes', $payload)
                    ? $payload['visit_notes']
                    : $visit->visit_notes,
                'handover_note' => array_key_exists('handover_note', $payload)
                    ? $payload['handover_note']
                    : $visit->handover_note,
                'unfinished_required_acknowledged' => $pendingRequired->isNotEmpty() && $acknowledged,
            ]);

            $visit->scheduledVisit()->update([
                'status' => ScheduledVisitStatus::Completed,
            ]);

            if (! $captured) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::GpsUnavailable,
                    'Clock-out GPS was not captured.',
                    null,
                    [
                        'location_status' => $status->value,
                        'reason' => $payload['unavailable_reason'] ?? null,
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

            return $visit->refresh();
        });
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
