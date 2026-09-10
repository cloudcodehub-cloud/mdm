<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait EmployeeFormRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function employeeFieldRules(?int $employeeId = null, bool $creating = false): array
    {
        $jobType = $this->input('job_type');
        $requiresLogin = in_array($jobType, [JobType::Dsp->value, JobType::Supervisor->value], true);
        $linkingUser = filled($this->input('user_id'));
        $creatingLogin = $this->boolean('create_login') || ($creating && $requiresLogin && ! $linkingUser);
        $employee = $employeeId !== null ? Employee::query()->find($employeeId) : null;
        $needsNewPassword = $creatingLogin && ($employee === null || $employee->user_id === null) && ! $linkingUser;

        return [
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employeeId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                $requiresLogin || $creatingLogin ? 'required' : 'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee?->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'hired_on' => ['nullable', 'date'],
            'terminated_on' => ['nullable', 'date', 'required_if:employment_status,'.EmploymentStatus::Terminated->value],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'job_type' => ['required', Rule::enum(JobType::class)],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('job_type', JobType::Supervisor->value)),
                Rule::notIn(array_filter([$employeeId])),
            ],
            'notes' => ['nullable', 'string'],
            'create_login' => ['sometimes', 'boolean'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'password' => $needsNewPassword
                ? ['required', 'string', Password::default(), 'confirmed']
                : ['nullable', 'string'],
            'password_confirmation' => [$needsNewPassword ? 'required' : 'nullable', 'string'],
        ];
    }

    protected function prepareEmployeePayload(): void
    {
        $this->merge([
            'create_login' => $this->boolean('create_login'),
            'supervisor_id' => $this->filled('supervisor_id') ? $this->input('supervisor_id') : null,
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
        ]);
    }
}
