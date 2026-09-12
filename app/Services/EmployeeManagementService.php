<?php

namespace App\Services;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeEducation;
use App\Models\EmployeeReference;
use App\Models\EmployeeSecurityIncident;
use App\Models\EmployeeWorkHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeManagementService
{
    public function __construct(
        private DspAvailabilityService $availability,
        private ProfilePhotoService $photos,
        private ProfileAttentionService $attention,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $attributes = $this->employeeAttributes($data);
            $attributes['employee_number'] = filled($data['employee_number'] ?? null)
                ? $data['employee_number']
                : Employee::nextEmployeeNumber();

            $employee = Employee::query()->create($attributes);
            $this->syncLoginAccount($employee, $data, creating: true);
            $this->syncRelated($employee, $data);
            $this->attention->syncEmployee($employee->fresh() ?? $employee);

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $presentKeys
     */
    public function update(Employee $employee, array $data, ?array $presentKeys = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $presentKeys): Employee {
            $attributes = $this->employeeAttributes($data, $presentKeys ?? array_keys($data));

            if (filled($data['employee_number'] ?? null)) {
                $attributes['employee_number'] = $data['employee_number'];
            }

            $employee->update($attributes);
            $this->applyEmploymentStatus($employee, EmploymentStatus::from($data['employment_status']), $data['terminated_on'] ?? null);
            $this->syncLoginAccount($employee->fresh() ?? $employee, $data, creating: false);
            $this->syncRelated($employee->fresh() ?? $employee, $data);
            $this->attention->syncEmployee($employee->fresh() ?? $employee);

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    public function updateStatus(Employee $employee, EmploymentStatus $status, ?string $terminatedOn): Employee
    {
        return DB::transaction(function () use ($employee, $status, $terminatedOn): Employee {
            $this->applyEmploymentStatus($employee, $status, $terminatedOn);

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelated(Employee $employee, array $data): void
    {
        if (($data['remove_photo'] ?? false) === true) {
            $this->photos->removeEmployee($employee);
        }

        if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
            $this->photos->storeEmployee($employee, $data['profile_photo']);
        }

        if ($employee->job_type === JobType::Dsp && isset($data['availability_days']) && is_array($data['availability_days'])) {
            $this->availability->replaceWeekly($employee, $data['availability_days']);
        }

        $this->syncEducations($employee, $this->relatedRows($data['educations'] ?? null));
        $this->syncReferences($employee, $this->relatedRows($data['references'] ?? null));
        $this->syncWorkHistories($employee, $this->relatedRows($data['work_histories'] ?? null));
        $this->syncIncidents($employee, $this->relatedRows($data['incidents'] ?? null));
        $this->syncOnboardingCredentials($employee, $this->relatedRows($data['credentials'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $presentKeys
     * @return array<string, mixed>
     */
    private function employeeAttributes(array $data, ?array $presentKeys = null): array
    {
        $cell = $this->nullableString($data['cell_phone'] ?? null);
        $phone = $this->nullableString($data['phone'] ?? null);
        $keys = $presentKeys === null ? array_keys($data) : $presentKeys;
        $has = fn (string $key): bool => in_array($key, $keys, true);

        $attributes = [
            'first_name' => $data['first_name'],
            'middle_name' => $this->nullableString($data['middle_name'] ?? null),
            'last_name' => $data['last_name'],
            'email' => $this->nullableString($data['email'] ?? null),
            'phone' => $has('cell_phone') ? ($cell ?? $phone) : ($has('phone') ? $phone : ($cell ?? $phone)),
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'address_line_1' => $this->nullableString($data['address_line_1'] ?? null),
            'address_line_2' => $this->nullableString($data['address_line_2'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'state' => $this->nullableString($data['state'] ?? null),
            'postal_code' => $this->nullableString($data['postal_code'] ?? null),
            'emergency_contact_name' => $this->nullableString($data['emergency_contact_name'] ?? null),
            'emergency_contact_relationship' => $this->nullableString($data['emergency_contact_relationship'] ?? null),
            'emergency_contact_phone' => $this->nullableString($data['emergency_contact_phone'] ?? null),
            'hired_on' => $data['hired_on'] ?? null,
            'job_title' => $this->nullableString($data['job_title'] ?? null),
            'job_type' => $data['job_type'],
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'notes' => $this->nullableString($data['notes'] ?? null),
            'employment_status' => $data['employment_status'] ?? EmploymentStatus::Active->value,
            'terminated_on' => $data['terminated_on'] ?? null,
        ];

        $optional = [
            'home_phone' => $this->nullableString($data['home_phone'] ?? null),
            'cell_phone' => $cell ?? $phone,
            'alternate_phone' => $this->nullableString($data['alternate_phone'] ?? null),
            'previous_address_line_1' => $this->nullableString($data['previous_address_line_1'] ?? null),
            'previous_city' => $this->nullableString($data['previous_city'] ?? null),
            'previous_state' => $this->nullableString($data['previous_state'] ?? null),
            'previous_postal_code' => $this->nullableString($data['previous_postal_code'] ?? null),
            'employment_type' => $data['employment_type'] ?? null,
            'preferred_shift_type' => $this->nullableString($data['preferred_shift_type'] ?? null),
            'desired_hours_per_week' => $data['desired_hours_per_week'] ?? null,
            'willing_long_term' => $data['willing_long_term'] ?? null,
            'willing_short_term' => $data['willing_short_term'] ?? null,
            'willing_pets' => $data['willing_pets'] ?? null,
            'willing_smoke' => $data['willing_smoke'] ?? null,
            'how_heard' => $this->nullableString($data['how_heard'] ?? null),
            'employment_interest' => $this->nullableString($data['employment_interest'] ?? null),
            'has_drivers_license' => $data['has_drivers_license'] ?? null,
            'license_state' => $this->nullableString($data['license_state'] ?? null),
            'license_number' => $this->nullableString($data['license_number'] ?? null),
            'vehicle_make_year' => $this->nullableString($data['vehicle_make_year'] ?? null),
            'insurance_company' => $this->nullableString($data['insurance_company'] ?? null),
            'insurance_policy_number' => $this->nullableString($data['insurance_policy_number'] ?? null),
            'has_moving_violations' => $data['has_moving_violations'] ?? null,
            'moving_violations_description' => $this->nullableString($data['moving_violations_description'] ?? null),
            'license_ever_suspended' => $data['license_ever_suspended'] ?? null,
            'license_suspension_explanation' => $this->nullableString($data['license_suspension_explanation'] ?? null),
            'may_contact_current_employer' => $data['may_contact_current_employer'] ?? null,
            'ohio_resident_5_years' => $data['ohio_resident_5_years'] ?? null,
            'residence_history' => $this->nullableString($data['residence_history'] ?? null),
            'used_other_names' => $data['used_other_names'] ?? null,
            'other_names' => $this->nullableString($data['other_names'] ?? null),
            'has_conviction' => $data['has_conviction'] ?? null,
            'security_comments' => $this->nullableString($data['security_comments'] ?? null),
        ];

        foreach ($optional as $key => $value) {
            if ($has($key)) {
                $attributes[$key] = $value;
            }
        }

        if ($has('ssn') && filled($data['ssn'] ?? null)) {
            $attributes['ssn'] = $this->nullableString($data['ssn']);
        }

        if ($has('alternate_ssn') && filled($data['alternate_ssn'] ?? null)) {
            $attributes['alternate_ssn'] = $this->nullableString($data['alternate_ssn']);
        }

        return $attributes;
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function syncEducations(Employee $employee, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $employee->educations()->delete();

        foreach ($rows as $index => $row) {
            if (! filled($row['institution_name'] ?? null)) {
                continue;
            }

            EmployeeEducation::query()->create([
                'employee_id' => $employee->id,
                'level' => $row['level'] ?? EducationLevel::HighSchool->value,
                'institution_name' => $row['institution_name'],
                'city' => $this->nullableString($row['city'] ?? null),
                'state' => $this->nullableString($row['state'] ?? null),
                'country' => $this->nullableString($row['country'] ?? null),
                'graduated' => $row['graduated'] ?? null,
                'years_completed' => $row['years_completed'] ?? null,
                'degree' => $this->nullableString($row['degree'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function syncReferences(Employee $employee, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $employee->personalReferences()->delete();

        foreach ($rows as $index => $row) {
            if (! filled($row['name'] ?? null)) {
                continue;
            }

            EmployeeReference::query()->create([
                'employee_id' => $employee->id,
                'name' => $row['name'],
                'address' => $this->nullableString($row['address'] ?? null),
                'home_phone' => $this->nullableString($row['home_phone'] ?? null),
                'work_phone' => $this->nullableString($row['work_phone'] ?? null),
                'relationship' => $this->nullableString($row['relationship'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function syncWorkHistories(Employee $employee, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $employee->workHistories()->delete();

        foreach ($rows as $index => $row) {
            if (! filled($row['employer'] ?? null) && ! filled($row['job_title'] ?? null)) {
                continue;
            }

            EmployeeWorkHistory::query()->create([
                'employee_id' => $employee->id,
                'started_on' => $row['started_on'] ?? null,
                'ended_on' => $row['ended_on'] ?? null,
                'job_title' => $this->nullableString($row['job_title'] ?? null),
                'employer' => $this->nullableString($row['employer'] ?? null),
                'employer_phone' => $this->nullableString($row['employer_phone'] ?? null),
                'employer_address' => $this->nullableString($row['employer_address'] ?? null),
                'reason_for_leaving' => $this->nullableString($row['reason_for_leaving'] ?? null),
                'job_duties' => $this->nullableString($row['job_duties'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function syncIncidents(Employee $employee, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $employee->securityIncidents()->delete();

        foreach ($rows as $index => $row) {
            if (! filled($row['incident'] ?? null) && ! filled($row['charge'] ?? null)) {
                continue;
            }

            EmployeeSecurityIncident::query()->create([
                'employee_id' => $employee->id,
                'incident' => $this->nullableString($row['incident'] ?? null),
                'city_state' => $this->nullableString($row['city_state'] ?? null),
                'charge' => $this->nullableString($row['charge'] ?? null),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    private function syncOnboardingCredentials(Employee $employee, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        foreach ($rows as $row) {
            if (! filled($row['name'] ?? null) || ! filled($row['type'] ?? null)) {
                continue;
            }

            $type = CredentialType::tryFrom((string) $row['type']);

            if ($type === null) {
                continue;
            }

            $credential = EmployeeCredential::query()->create([
                'employee_id' => $employee->id,
                'type' => $type,
                'name' => $row['name'],
                'issuer' => $this->nullableString($row['issuer'] ?? null),
                'credential_number' => $this->nullableString($row['credential_number'] ?? null),
                'issued_on' => $row['issued_on'] ?? null,
                'expires_on' => $row['expires_on'] ?? null,
                'status' => $row['status'] ?? CredentialStatus::Active->value,
                'notes' => $this->nullableString($row['notes'] ?? null),
            ]);

            if (($row['document'] ?? null) instanceof UploadedFile) {
                $this->photos->storeCredentialDocument($credential, $row['document']);
            }
        }
    }

    private function applyEmploymentStatus(Employee $employee, EmploymentStatus $status, mixed $terminatedOn): void
    {
        $terminatedDate = is_string($terminatedOn) && $terminatedOn !== '' ? $terminatedOn : null;

        $employee->fill([
            'employment_status' => $status,
            'terminated_on' => $status === EmploymentStatus::Terminated
                ? ($terminatedDate ?? now()->toDateString())
                : null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncLoginAccount(Employee $employee, array $data, bool $creating): void
    {
        $jobType = $employee->job_type;
        $createLogin = (bool) ($data['create_login'] ?? false);
        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int) $data['user_id'] : null;

        if ($userId !== null) {
            $this->linkExistingUser($employee, $userId, $jobType);

            return;
        }

        if ($employee->user_id !== null) {
            $this->syncLinkedUser($employee);

            return;
        }

        $needsLogin = $createLogin || ($creating && $jobType->requiresLogin());

        if (! $needsLogin) {
            return;
        }

        if (! $jobType->requiresLogin()) {
            throw ValidationException::withMessages([
                'create_login' => 'A login account can only be created for Admin, Supervisor, or DSP employees.',
            ]);
        }

        $email = $employee->email;

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'email' => 'An email address is required to create a login account.',
            ]);
        }

        $password = $data['password'] ?? null;

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages([
                'password' => 'A password is required to create the login account.',
            ]);
        }

        $user = User::query()->create([
            'name' => $employee->full_name,
            'email' => $email,
            'password' => $password,
            'role' => $jobType->toRole(),
            'email_verified_at' => now(),
        ]);

        $employee->update(['user_id' => $user->id]);
    }

    private function linkExistingUser(Employee $employee, int $userId, JobType $jobType): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->employee !== null && $user->employee->id !== $employee->id) {
            throw ValidationException::withMessages([
                'user_id' => 'That login account is already linked to another employee.',
            ]);
        }

        if ($user->isAdmin() && $jobType !== JobType::Admin) {
            throw ValidationException::withMessages([
                'user_id' => 'Admin login accounts can only be linked to Admin employee profiles.',
            ]);
        }

        if ($jobType->requiresLogin() && $user->role !== $jobType->toRole()) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected login account role must match the employee system role.',
            ]);
        }

        $employee->update(['user_id' => $user->id]);
        $this->syncLinkedUser($employee->fresh() ?? $employee);
    }

    private function syncLinkedUser(Employee $employee): void
    {
        $user = $employee->user;

        if ($user === null) {
            return;
        }

        $updates = [
            'name' => $employee->full_name,
        ];

        if (filled($employee->email)) {
            $updates['email'] = $employee->email;
        }

        if ($employee->job_type->requiresLogin()) {
            $updates['role'] = $employee->job_type->toRole();
        }

        $user->update($updates);
    }

    /**
     * @param  mixed  $rows
     * @return list<array<string, mixed>>|null
     */
    private function relatedRows(mixed $rows): ?array
    {
        if (! is_array($rows)) {
            return null;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
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
