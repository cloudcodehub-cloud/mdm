<?php

namespace App\Services;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

class VisitExceptionService
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        Visit $visit,
        VisitExceptionType $type,
        string $message,
        ?VisitTask $task = null,
        ?array $context = null,
    ): VisitException {
        $existing = $this->existing($visit, $type, $task);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return VisitException::query()->create([
                'visit_id' => $visit->id,
                'visit_task_id' => $task?->id,
                'type' => $type,
                'status' => VisitExceptionStatus::Open,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing($visit, $type, $task);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existing(Visit $visit, VisitExceptionType $type, ?VisitTask $task): ?VisitException
    {
        $taskId = $task?->id;

        return VisitException::query()
            ->where('visit_id', $visit->id)
            ->where('type', $type)
            ->when(
                $taskId === null,
                fn (Builder $query): Builder => $query->whereNull('visit_task_id'),
                fn (Builder $query): Builder => $query->where('visit_task_id', $taskId),
            )
            ->first();
    }
}
