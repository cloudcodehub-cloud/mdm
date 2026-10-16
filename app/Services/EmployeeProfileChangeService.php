<?php

namespace App\Services;

use App\Enums\EmployeeAdminHistoryCategory;
use App\Enums\EmployeeProfileChangeField;
use App\Enums\ReviewStatus;
use App\Models\Employee;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeProfileChangeService
{
    public function __construct(private EmployeeAdminHistoryService $history) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(Employee $employee, User $actor, array $payload): EmployeeProfileChangeRequest
    {
        if (! $actor->isDsp() || $actor->employee === null || ! $actor->employee->is($employee)) {
            throw ValidationException::withMessages([
                'employee' => 'Only the employee may submit personal information change requests for their own record.',
            ]);
        }

        $proposed = $this->extractAllowed($payload);
        if ($proposed === []) {
            throw ValidationException::withMessages([
                'proposed_values' => 'Provide at least one personal/contact field to change.',
            ]);
        }

        $current = [];
        foreach (array_keys($proposed) as $field) {
            $current[$field] = $employee->{$field};
        }

        $changed = [];
        foreach ($proposed as $field => $value) {
            $existing = $current[$field] ?? null;
            $existingNormalized = is_string($existing) ? trim($existing) : $existing;
            if ($existingNormalized !== $value) {
                $changed[$field] = $value;
            }
        }

        if ($changed === []) {
            throw ValidationException::withMessages([
                'proposed_values' => 'No changes detected versus the current authoritative profile.',
            ]);
        }

        if ($employee->profileChangeRequests()->pending()->exists()) {
            throw ValidationException::withMessages([
                'proposed_values' => 'A pending personal information request already exists. Wait for review or ask a supervisor to resolve it.',
            ]);
        }

        $currentSnapshot = [];
        foreach (array_keys($changed) as $field) {
            $currentSnapshot[$field] = $current[$field];
        }

        return EmployeeProfileChangeRequest::query()->create([
            'employee_id' => $employee->id,
            'status' => ReviewStatus::Pending,
            'proposed_values' => $changed,
            'current_values' => $currentSnapshot,
            'reason' => $this->nullableString($payload['reason'] ?? null),
            'requested_by_user_id' => $actor->id,
            'submitted_at' => now(),
        ]);
    }

    public function approve(EmployeeProfileChangeRequest $request, User $reviewer, ?string $reviewNote = null): Employee
    {
        $this->assertPending($request);
        $request->loadMissing('employee');

        return DB::transaction(function () use ($request, $reviewer, $reviewNote): Employee {
            $employee = $request->employee;
            $proposed = $request->proposed_values;

            $employee->forceFill($proposed)->save();

            $request->forceFill([
                'status' => ReviewStatus::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->nullableString($reviewNote),
            ])->save();

            $labels = [];
            foreach (array_keys($proposed) as $field) {
                $enum = EmployeeProfileChangeField::tryFrom($field);
                $labels[] = $enum?->label() ?? $field;
            }

            $this->history->record($employee, $reviewer, [
                'category' => EmployeeAdminHistoryCategory::ProfileChange,
                'summary' => 'Personal information change approved',
                'old_value' => json_encode($request->current_values, JSON_THROW_ON_ERROR),
                'new_value' => json_encode($proposed, JSON_THROW_ON_ERROR),
                'reason' => $request->reason,
                'meta' => [
                    'fields' => $labels,
                    'request_id' => $request->id,
                    'review_note' => $this->nullableString($reviewNote),
                ],
                'subject' => $request,
            ]);

            return $employee->fresh() ?? $employee;
        });
    }

    public function reject(EmployeeProfileChangeRequest $request, User $reviewer, ?string $reviewNote = null): EmployeeProfileChangeRequest
    {
        $this->assertPending($request);

        $request->loadMissing('employee');

        $request->forceFill([
            'status' => ReviewStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->nullableString($reviewNote),
        ])->save();

        $this->history->record($request->employee, $reviewer, [
            'category' => EmployeeAdminHistoryCategory::ProfileChange,
            'summary' => 'Personal information change rejected',
            'old_value' => json_encode($request->current_values),
            'new_value' => json_encode($request->proposed_values),
            'reason' => $request->reason,
            'meta' => [
                'request_id' => $request->id,
                'review_note' => $this->nullableString($reviewNote),
            ],
            'subject' => $request,
        ]);

        return $request->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForReview(EmployeeProfileChangeRequest $request): array
    {
        $fields = [];
        foreach ($request->proposed_values as $key => $value) {
            $enum = EmployeeProfileChangeField::tryFrom((string) $key);
            $fields[] = [
                'field' => $key,
                'label' => $enum?->label() ?? (string) $key,
                'current' => $request->current_values[$key] ?? null,
                'proposed' => $value,
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    private function extractAllowed(array $payload): array
    {
        $out = [];

        foreach (EmployeeProfileChangeField::cases() as $field) {
            if (! array_key_exists($field->value, $payload)) {
                continue;
            }

            $raw = $payload[$field->value];
            if ($raw === null) {
                $out[$field->value] = null;

                continue;
            }

            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }

            $trimmed = trim((string) $raw);
            $out[$field->value] = $trimmed === '' ? null : $trimmed;
        }

        return $out;
    }

    private function assertPending(EmployeeProfileChangeRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending personal information requests can be reviewed.',
            ]);
        }
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
