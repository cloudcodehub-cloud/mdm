<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        if (! $employee instanceof Employee) {
            return false;
        }

        $status = EmploymentStatus::tryFrom((string) (
            $this->input('employment_status') ?? $this->input('status') ?? ''
        ));

        if (in_array($status, [EmploymentStatus::Archived, EmploymentStatus::ArchivePending], true)) {
            return $this->user()?->can('archive', $employee) ?? false;
        }

        return $this->user()?->can('manageLifecycle', $employee) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $status = EmploymentStatus::tryFrom((string) (
            $this->input('employment_status') ?? $this->input('status') ?? ''
        ));
        $requiresReason = in_array($status, [
            EmploymentStatus::Suspended,
            EmploymentStatus::EmploymentEnded,
            EmploymentStatus::Archived,
        ], true);

        return [
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'reason' => [$requiresReason ? 'required' : 'nullable', 'string', 'max:1000'],
            'effective_on' => ['nullable', 'date'],
            'terminated_on' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('employment_status') && $this->filled('status')) {
            $this->merge([
                'employment_status' => $this->input('status'),
            ]);
        }
    }
}
