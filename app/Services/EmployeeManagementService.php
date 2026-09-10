<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeManagementService
{
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

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $attributes = $this->employeeAttributes($data);

            if (filled($data['employee_number'] ?? null)) {
                $attributes['employee_number'] = $data['employee_number'];
            }

            $employee->update($attributes);
            $this->applyEmploymentStatus($employee, EmploymentStatus::from($data['employment_status']), $data['terminated_on'] ?? null);
            $this->syncLoginAccount($employee->fresh() ?? $employee, $data, creating: false);

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
     * @return array<string, mixed>
     */
    private function employeeAttributes(array $data): array
    {
        return [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'hired_on' => $data['hired_on'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'job_type' => $data['job_type'],
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'employment_status' => $data['employment_status'] ?? EmploymentStatus::Active->value,
            'terminated_on' => $data['terminated_on'] ?? null,
        ];
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
                'create_login' => 'A login account can only be created for Supervisor or DSP employees.',
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

        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'user_id' => 'Admin login accounts cannot be linked to an employee profile.',
            ]);
        }

        if ($jobType->requiresLogin() && $user->role !== $jobType->toRole()) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected login account role must match the employee job type.',
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
}
