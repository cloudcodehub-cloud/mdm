<?php

namespace App\Services;

use App\Enums\VisitExceptionType;
use App\Enums\VisitTaskStatus;
use App\Models\SkipReason;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitTaskService
{
    public function __construct(private VisitExceptionService $exceptions) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(User $user, Visit $visit, VisitTask $task, array $payload): VisitTask
    {
        $this->assertAssignedTask($visit, $task);

        return DB::transaction(function () use ($user, $visit, $task, $payload): VisitTask {
            $this->assertActor($user, $visit);
            /** @var VisitTask $task */
            $task = VisitTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $lockedVisit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $this->assertVisitInProgress($lockedVisit);

            if ($task->status === VisitTaskStatus::Completed) {
                return $task;
            }

            if ($task->status !== VisitTaskStatus::Pending) {
                throw ValidationException::withMessages([
                    'task' => 'This task has already been recorded and cannot be completed.',
                ]);
            }

            $note = $payload['completion_note'] ?? null;

            if ($task->note_required && (! is_string($note) || trim($note) === '')) {
                throw ValidationException::withMessages([
                    'completion_note' => 'A note is required for this task.',
                ]);
            }

            $task->update([
                'status' => VisitTaskStatus::Completed,
                'completed_at' => now(),
                'completion_note' => $payload['completion_note'] ?? null,
            ]);

            return $task->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function skip(User $user, Visit $visit, VisitTask $task, array $payload): VisitTask
    {
        $this->assertAssignedTask($visit, $task);

        return DB::transaction(function () use ($user, $visit, $task, $payload): VisitTask {
            $this->assertActor($user, $visit);
            /** @var VisitTask $task */
            $task = VisitTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $visit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $this->assertVisitInProgress($visit);

            if ($task->status === VisitTaskStatus::Skipped) {
                return $task;
            }

            if ($task->status !== VisitTaskStatus::Pending) {
                throw ValidationException::withMessages([
                    'task' => 'This task has already been recorded and cannot be skipped.',
                ]);
            }

            if (! $task->can_skip) {
                throw ValidationException::withMessages([
                    'task' => 'This task cannot be skipped.',
                ]);
            }

            $reason = SkipReason::query()
                ->active()
                ->whereKey($payload['skip_reason_id'])
                ->first();

            if ($reason === null) {
                throw ValidationException::withMessages([
                    'skip_reason_id' => 'Select a current skip reason.',
                ]);
            }

            $comment = isset($payload['skip_comment']) && is_string($payload['skip_comment'])
                ? trim($payload['skip_comment'])
                : null;
            $comment = $comment === '' ? null : $comment;

            if ($reason->requiresExplanation() && $comment === null) {
                throw ValidationException::withMessages([
                    'skip_comment' => $reason->code === SkipReason::CLIENT_REFUSED
                        ? 'Record the client refusal explanation.'
                        : 'A comment is required for this skip reason.',
                ]);
            }

            $task->update([
                'status' => VisitTaskStatus::Skipped,
                'skipped_at' => now(),
                'skip_reason_id' => $reason->id,
                'skip_comment' => $comment,
            ]);

            $task->setRelation('skipReason', $reason);

            if ($reason->code === SkipReason::CLIENT_REFUSED) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::ClientRefusal,
                    'Client refused a care-plan task.',
                    $task,
                    [
                        'skip_reason_code' => $reason->code,
                        'explanation' => $comment,
                    ],
                );
            }

            if ($task->is_required || $task->is_critical) {
                $this->exceptions->record(
                    $visit,
                    VisitExceptionType::CriticalTaskSkipped,
                    'A required care-plan task was skipped.',
                    $task,
                    [
                        'skip_reason_code' => $reason->code,
                        'title' => $task->title,
                    ],
                );
            }

            return $task->refresh();
        });
    }

    private function assertAssignedTask(Visit $visit, VisitTask $task): void
    {
        if ($task->visit_id !== $visit->id) {
            abort(404);
        }
    }

    private function assertActor(User $user, Visit $visit): void
    {
        $employee = $user->employee;

        if ($employee === null || ! $employee->isActiveDsp() || $visit->employee_id !== $employee->id) {
            abort(403);
        }
    }

    private function assertVisitInProgress(Visit $visit): void
    {
        if (! $visit->isInProgress()) {
            throw ValidationException::withMessages([
                'task' => 'This visit is no longer in progress.',
            ]);
        }
    }
}
