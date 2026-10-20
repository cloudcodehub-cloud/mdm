<?php

namespace App\Services;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitSafetyCheck;
use App\Models\VisitTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

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
        ?VisitSafetyCheck $safetyCheck = null,
    ): VisitException {
        $existing = $this->existing($visit, $type, $task, $safetyCheck);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return VisitException::query()->create([
                'visit_id' => $visit->id,
                'visit_task_id' => $task?->id,
                'visit_safety_check_id' => $safetyCheck?->id,
                'type' => $type,
                'status' => VisitExceptionStatus::Open,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing($visit, $type, $task, $safetyCheck);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function review(VisitException $exception, User $user, ?string $notes = null): VisitException
    {
        if (! $exception->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'Only open exceptions can be marked reviewed.',
            ]);
        }

        $notes = $this->normalizeNotes($notes);

        $exception->forceFill([
            'status' => VisitExceptionStatus::Reviewed,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
            'status_history' => $this->appendHistory($exception, VisitExceptionStatus::Reviewed, $user, $notes),
        ])->save();

        return $exception->refresh();
    }

    public function resolve(VisitException $exception, User $user, ?string $notes = null): VisitException
    {
        if ($exception->isResolved()) {
            throw ValidationException::withMessages([
                'status' => 'This exception is already resolved.',
            ]);
        }

        $notes = $this->normalizeNotes($notes);

        $exception->forceFill([
            'status' => VisitExceptionStatus::Resolved,
            'resolved_by_user_id' => $user->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
            'status_history' => $this->appendHistory($exception, VisitExceptionStatus::Resolved, $user, $notes),
        ])->save();

        return $exception->refresh();
    }

    public function followUp(VisitException $exception, User $user, string $notes): VisitException
    {
        if ($exception->isResolved()) {
            throw ValidationException::withMessages([
                'status' => 'Resolved exceptions cannot receive additional follow-up notes.',
            ]);
        }

        $notes = $this->normalizeNotes($notes);

        if ($notes === null) {
            throw ValidationException::withMessages([
                'notes' => 'Enter a follow-up note.',
            ]);
        }

        $exception->forceFill([
            'status_history' => $this->appendHistory($exception, $exception->status, $user, $notes),
        ])->save();

        return $exception->refresh();
    }

    /**
     * MDM 03 §8 — Admin reopen of a resolved Visit Exception.
     * Preserves original resolution; does not reopen the Visit, EVV, or payroll.
     *
     * @param  array{reopen_reason: string}  $payload
     */
    public function reopen(VisitException $exception, User $user, array $payload): VisitException
    {
        if (! $user->isAdmin()) {
            abort(403);
        }

        if (! $exception->isResolved()) {
            throw ValidationException::withMessages([
                'exception' => 'Only a resolved Visit Exception may be reopened.',
            ]);
        }

        $reason = trim((string) ($payload['reopen_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reopen_reason' => 'A reopen reason is required.',
            ]);
        }

        $now = now();

        $exception->forceFill([
            'original_resolved_at' => $exception->original_resolved_at ?? $exception->resolved_at,
            'original_resolved_by_user_id' => $exception->original_resolved_by_user_id ?? $exception->resolved_by_user_id,
            'original_resolution_notes' => $exception->original_resolution_notes ?? $exception->resolution_notes,
            'status' => VisitExceptionStatus::Open,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
            'reopened_at' => $now,
            'reopened_by_user_id' => $user->id,
            'reopen_reason' => $reason,
            'status_history' => $this->appendHistory(
                $exception,
                VisitExceptionStatus::Open,
                $user,
                'Reopened: '.$reason,
            ),
        ])->save();

        return $exception->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appendHistory(
        VisitException $exception,
        VisitExceptionStatus $status,
        User $user,
        ?string $notes,
    ): array {
        $history = $exception->status_history ?? [];

        $history[] = [
            'status' => $status->value,
            'at' => now()->toIso8601String(),
            'user_id' => $user->id,
            'user_name' => $user->name,
            'notes' => $notes,
        ];

        return $history;
    }

    private function normalizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);

        return $notes === '' ? null : $notes;
    }

    private function existing(
        Visit $visit,
        VisitExceptionType $type,
        ?VisitTask $task,
        ?VisitSafetyCheck $safetyCheck,
    ): ?VisitException {
        $taskId = $task?->id;
        $safetyId = $safetyCheck?->id;

        return VisitException::query()
            ->where('visit_id', $visit->id)
            ->where('type', $type)
            ->when(
                $taskId !== null,
                fn (Builder $query): Builder => $query->where('visit_task_id', $taskId),
                fn (Builder $query): Builder => $query->whereNull('visit_task_id'),
            )
            ->when(
                $safetyId !== null,
                fn (Builder $query): Builder => $query->where('visit_safety_check_id', $safetyId),
                fn (Builder $query): Builder => $query->whereNull('visit_safety_check_id'),
            )
            ->first();
    }
}
