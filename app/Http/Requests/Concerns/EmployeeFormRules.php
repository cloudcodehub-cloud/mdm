<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\JobType;
use App\Enums\PreferredDaypart;
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
        $requiresLogin = in_array($jobType, [JobType::Dsp->value, JobType::Supervisor->value, JobType::Admin->value], true);
        $linkingUser = filled($this->input('user_id'));
        $creatingLogin = $this->boolean('create_login') || ($creating && $requiresLogin && ! $linkingUser);
        $employee = $employeeId !== null ? Employee::query()->find($employeeId) : null;
        $needsNewPassword = $creatingLogin && ($employee === null || $employee->user_id === null) && ! $linkingUser;
        $hasLicense = $this->boolean('has_drivers_license');
        $hasViolations = $this->boolean('has_moving_violations');
        $licenseSuspended = $this->boolean('license_ever_suspended');
        $usedOtherNames = $this->boolean('used_other_names');

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
            'home_phone' => ['nullable', 'string', 'max:50'],
            'cell_phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'previous_address_line_1' => ['nullable', 'string', 'max:255'],
            'previous_city' => ['nullable', 'string', 'max:100'],
            'previous_state' => ['nullable', 'string', 'max:50'],
            'previous_postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'hired_on' => ['nullable', 'date'],
            'terminated_on' => ['nullable', 'date', 'required_if:employment_status,'.EmploymentStatus::Terminated->value],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'job_type' => ['required', Rule::enum(JobType::class)],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'preferred_shift_type' => ['nullable', 'string', 'max:50'],
            'desired_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:80'],
            'willing_long_term' => ['nullable', 'boolean'],
            'willing_short_term' => ['nullable', 'boolean'],
            'willing_pets' => ['nullable', 'boolean'],
            'willing_smoke' => ['nullable', 'boolean'],
            'how_heard' => ['nullable', 'string', 'max:255'],
            'employment_interest' => ['nullable', 'string'],
            'has_drivers_license' => ['nullable', 'boolean'],
            'license_state' => [$hasLicense ? 'required' : 'nullable', 'string', 'max:50'],
            'license_number' => [$hasLicense ? 'required' : 'nullable', 'string', 'max:100'],
            'vehicle_make_year' => ['nullable', 'string', 'max:100'],
            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_policy_number' => ['nullable', 'string', 'max:100'],
            'has_moving_violations' => ['nullable', 'boolean'],
            'moving_violations_description' => [$hasViolations ? 'required' : 'nullable', 'string'],
            'license_ever_suspended' => ['nullable', 'boolean'],
            'license_suspension_explanation' => [$licenseSuspended ? 'required' : 'nullable', 'string'],
            'may_contact_current_employer' => ['nullable', 'boolean'],
            'ohio_resident_5_years' => ['nullable', 'boolean'],
            'residence_history' => ['nullable', 'string'],
            'used_other_names' => ['nullable', 'boolean'],
            'other_names' => [$usedOtherNames ? 'required' : 'nullable', 'string'],
            'ssn' => ['nullable', 'string', 'max:32'],
            'alternate_ssn' => ['nullable', 'string', 'max:32'],
            'has_conviction' => ['nullable', 'boolean'],
            'security_comments' => ['nullable', 'string'],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('job_type', JobType::Supervisor->value)),
                Rule::notIn(array_filter([$employeeId])),
            ],
            'notes' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
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
            'availability_days' => ['sometimes', 'array'],
            'availability_days.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'availability_days.*.is_available' => ['sometimes', 'boolean'],
            'availability_days.*.starts_at' => ['nullable', 'date_format:H:i'],
            'availability_days.*.ends_at' => ['nullable', 'date_format:H:i'],
            'availability_days.*.preferred_daypart' => ['nullable', Rule::enum(PreferredDaypart::class)],
            'educations' => ['sometimes', 'array'],
            'educations.*.level' => ['required', Rule::enum(EducationLevel::class)],
            'educations.*.institution_name' => ['nullable', 'string', 'max:255'],
            'educations.*.city' => ['nullable', 'string', 'max:100'],
            'educations.*.state' => ['nullable', 'string', 'max:50'],
            'educations.*.country' => ['nullable', 'string', 'max:100'],
            'educations.*.graduated' => ['nullable', 'boolean'],
            'educations.*.years_completed' => ['nullable', 'integer', 'min:0', 'max:12'],
            'educations.*.degree' => ['nullable', 'string', 'max:255'],
            'references' => ['sometimes', 'array'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.address' => ['nullable', 'string', 'max:255'],
            'references.*.home_phone' => ['nullable', 'string', 'max:50'],
            'references.*.work_phone' => ['nullable', 'string', 'max:50'],
            'references.*.relationship' => ['nullable', 'string', 'max:100'],
            'work_histories' => ['sometimes', 'array'],
            'work_histories.*.started_on' => ['nullable', 'date'],
            'work_histories.*.ended_on' => ['nullable', 'date'],
            'work_histories.*.job_title' => ['nullable', 'string', 'max:255'],
            'work_histories.*.employer' => ['nullable', 'string', 'max:255'],
            'work_histories.*.employer_phone' => ['nullable', 'string', 'max:50'],
            'work_histories.*.employer_address' => ['nullable', 'string', 'max:255'],
            'work_histories.*.reason_for_leaving' => ['nullable', 'string', 'max:255'],
            'work_histories.*.job_duties' => ['nullable', 'string'],
            'incidents' => ['sometimes', 'array'],
            'incidents.*.incident' => ['nullable', 'string', 'max:255'],
            'incidents.*.city_state' => ['nullable', 'string', 'max:255'],
            'incidents.*.charge' => ['nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*.type' => ['nullable', Rule::enum(CredentialType::class)],
            'credentials.*.name' => ['nullable', 'string', 'max:255'],
            'credentials.*.issuer' => ['nullable', 'string', 'max:255'],
            'credentials.*.credential_number' => ['nullable', 'string', 'max:255'],
            'credentials.*.issued_on' => ['nullable', 'date'],
            'credentials.*.expires_on' => ['nullable', 'date'],
            'credentials.*.status' => ['nullable', Rule::enum(CredentialStatus::class)],
            'credentials.*.notes' => ['nullable', 'string'],
            'credentials.*.document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function prepareEmployeePayload(): void
    {
        $booleans = [
            'create_login',
            'remove_photo',
            'willing_long_term',
            'willing_short_term',
            'willing_pets',
            'willing_smoke',
            'has_drivers_license',
            'has_moving_violations',
            'license_ever_suspended',
            'may_contact_current_employer',
            'ohio_resident_5_years',
            'used_other_names',
            'has_conviction',
        ];

        $merge = [
            'supervisor_id' => $this->filled('supervisor_id') ? $this->input('supervisor_id') : null,
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
        ];

        foreach ($booleans as $field) {
            if ($this->exists($field)) {
                $merge[$field] = $this->boolean($field);
            }
        }

        $this->merge($merge);
    }
}
