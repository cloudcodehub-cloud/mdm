<?php

namespace App\Services;

use App\Enums\EmployeeAdminHistoryCategory;
use App\Models\Employee;
use App\Models\EmployeeAdminHistoryEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EmployeeAdminHistoryService
{
    /**
     * @param  array{
     *     category: EmployeeAdminHistoryCategory|string,
     *     summary: string,
     *     old_value?: string|null,
     *     new_value?: string|null,
     *     reason?: string|null,
     *     effective_on?: string|null,
     *     meta?: array<string, mixed>|null,
     *     subject?: Model|null,
     * }  $payload
     */
    public function record(Employee $employee, ?User $actor, array $payload): EmployeeAdminHistoryEvent
    {
        $category = $payload['category'] instanceof EmployeeAdminHistoryCategory
            ? $payload['category']
            : EmployeeAdminHistoryCategory::from((string) $payload['category']);

        $subject = $payload['subject'] ?? null;

        return EmployeeAdminHistoryEvent::query()->create([
            'employee_id' => $employee->id,
            'category' => $category,
            'summary' => trim($payload['summary']),
            'old_value' => $this->nullableString($payload['old_value'] ?? null),
            'new_value' => $this->nullableString($payload['new_value'] ?? null),
            'reason' => $this->nullableString($payload['reason'] ?? null),
            'effective_on' => $payload['effective_on'] ?? null,
            'actor_user_id' => $actor?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $payload['meta'] ?? null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForEmployee(Employee $employee, int $limit = 100): array
    {
        /** @var Collection<int, EmployeeAdminHistoryEvent> $events */
        $events = EmployeeAdminHistoryEvent::query()
            ->where('employee_id', $employee->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $events->map(function (EmployeeAdminHistoryEvent $event): array {
            return [
                'id' => $event->id,
                'category' => $event->category->value,
                'category_label' => $event->category->label(),
                'summary' => $event->summary,
                'old_value' => $event->old_value,
                'new_value' => $event->new_value,
                'reason' => $event->reason,
                'effective_on' => $event->effective_on?->toDateString(),
                'actor_name' => $event->actor?->name,
                'occurred_at' => $event->created_at?->toIso8601String(),
                'occurred_at_label' => $event->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            ];
        })->values()->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
