<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\VisitExceptionStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\User;
use App\Models\VisitException;
use App\Support\DirectoryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupervisorManagementService
{
    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    public function paginateEligible(?string $search = null): LengthAwarePaginator
    {
        return $this->eligibleQuery()
            ->search($search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();
    }

    public function findEligible(int $employeeId): ?Employee
    {
        return $this->eligibleQuery()->whereKey($employeeId)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeEligiblePage(LengthAwarePaginator $employees): array
    {
        $rows = [];

        foreach ($employees->getCollection() as $employee) {
            $rows[] = $this->serializeCandidate($employee);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCandidate(Employee $employee): array
    {
        $employee->loadMissing('user');

        return [
            ...DirectoryPresenter::employeeSummary($employee),
            'login_role' => $employee->user?->role->value,
            'login_role_label' => $employee->user?->role->value,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function replacementOptions(Employee $except): array
    {
        return $this->replacementQuery($except)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $supervisor): array => [
                'id' => $supervisor->id,
                'name' => $supervisor->full_name,
                'employee_number' => $supervisor->employee_number,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function responsibilities(Employee $supervisor): array
    {
        $dsps = $supervisor->reports()
            ->with('user')
            ->where('job_type', JobType::Dsp)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $clients = $supervisor->supervisedClients()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $pendingAvailability = DspAvailabilityRequest::query()
            ->where('status', ReviewStatus::Pending)
            ->whereHas('employee', fn (Builder $query) => $query->where('supervisor_id', $supervisor->id))
            ->count();

        $pendingTimeOff = EmployeeTimeOff::query()
            ->where('status', ReviewStatus::Pending)
            ->whereHas('employee', fn (Builder $query) => $query->where('supervisor_id', $supervisor->id))
            ->count();

        $pendingCorrections = AttendanceCorrection::query()
            ->pending()
            ->whereHas('scheduledVisit.employee', fn (Builder $query) => $query->where('supervisor_id', $supervisor->id))
            ->count();

        $openExceptions = VisitException::query()
            ->where('status', '!=', VisitExceptionStatus::Resolved)
            ->where(function (Builder $query) use ($supervisor): void {
                $query->whereHas('visit.scheduledVisit', function (Builder $visit) use ($supervisor): void {
                    $visit->where('supervisor_id', $supervisor->id)
                        ->orWhere('employee_id', $supervisor->id)
                        ->orWhereIn('employee_id', $supervisor->reports()->select('id'))
                        ->orWhereIn('client_id', $supervisor->supervisedClients()->select('id'));
                });
            })
            ->count();

        return [
            'dsps' => $dsps->map(fn (Employee $dsp): array => DirectoryPresenter::employeeSummary($dsp))->values()->all(),
            'clients' => $clients->map(fn (Client $client): array => DirectoryPresenter::clientSummary($client))->values()->all(),
            'dsp_count' => $dsps->count(),
            'client_count' => $clients->count(),
            'other_report_count' => $supervisor->reports()->where('job_type', '!=', JobType::Dsp)->count(),
            'pending_availability_count' => $pendingAvailability,
            'pending_time_off_count' => $pendingTimeOff,
            'pending_attendance_correction_count' => $pendingCorrections,
            'open_exception_count' => $openExceptions,
            'blocks_revocation' => $this->hasBlockingResponsibilities($supervisor),
        ];
    }

    public function hasBlockingResponsibilities(Employee $supervisor): bool
    {
        return $supervisor->reports()->exists()
            || $supervisor->supervisedClients()->exists();
    }

    public function promote(Employee $employee, User $actor): Employee
    {
        if ($this->findEligible($employee->id) === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Only an active DSP employee with a linked DSP login can be appointed as Supervisor.',
            ]);
        }

        $jobTitle = $employee->job_title;
        $previousRole = $employee->user?->role->value;

        return DB::transaction(function () use ($employee, $actor, $jobTitle, $previousRole): Employee {
            $employee->job_type = JobType::Supervisor;
            $employee->job_title = $jobTitle;
            $this->appendHistory($employee, [
                'action' => 'promoted',
                'previous_role' => $previousRole,
                'new_role' => Role::Supervisor->value,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'at' => now()->toIso8601String(),
                'replacement_employee_id' => null,
                'replacement_name' => null,
            ]);
            $employee->save();

            $this->syncLoginRole($employee, JobType::Supervisor);

            return $employee->fresh(['user']) ?? $employee;
        });
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function reassignTeam(Employee $from, Employee $replacement, array $employeeIds, User $actor): void
    {
        $replacement = $this->assertReplacement($from, $replacement);

        $ids = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one DSP to transfer.',
            ]);
        }

        $dsps = Employee::query()
            ->whereIn('id', $ids)
            ->where('supervisor_id', $from->id)
            ->where('job_type', JobType::Dsp)
            ->get();

        if ($dsps->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Each selected DSP must currently report to this Supervisor.',
            ]);
        }

        DB::transaction(function () use ($from, $replacement, $dsps, $actor): void {
            Employee::query()
                ->whereIn('id', $dsps->modelKeys())
                ->update(['supervisor_id' => $replacement->id]);

            $this->appendHistory($from, [
                'action' => 'reassigned_team',
                'previous_role' => Role::Supervisor->value,
                'new_role' => Role::Supervisor->value,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'at' => now()->toIso8601String(),
                'replacement_employee_id' => $replacement->id,
                'replacement_name' => $replacement->full_name,
                'employee_ids' => $dsps->modelKeys(),
            ]);
            $from->save();
        });
    }

    /**
     * @param  list<int>  $clientIds
     */
    public function reassignCaseload(Employee $from, Employee $replacement, array $clientIds, User $actor): void
    {
        $replacement = $this->assertReplacement($from, $replacement);

        $ids = array_values(array_unique(array_map('intval', $clientIds)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'client_ids' => 'Select at least one client to transfer.',
            ]);
        }

        $clients = Client::query()
            ->whereIn('id', $ids)
            ->where('supervisor_id', $from->id)
            ->get();

        if ($clients->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'client_ids' => 'Each selected client must currently be assigned to this Supervisor.',
            ]);
        }

        DB::transaction(function () use ($from, $replacement, $clients, $actor): void {
            Client::query()
                ->whereIn('id', $clients->modelKeys())
                ->update(['supervisor_id' => $replacement->id]);

            $this->appendHistory($from, [
                'action' => 'reassigned_caseload',
                'previous_role' => Role::Supervisor->value,
                'new_role' => Role::Supervisor->value,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'at' => now()->toIso8601String(),
                'replacement_employee_id' => $replacement->id,
                'replacement_name' => $replacement->full_name,
                'client_ids' => $clients->modelKeys(),
            ]);
            $from->save();
        });
    }

    public function revoke(Employee $supervisor, User $actor, ?Employee $replacement): Employee
    {
        if ($supervisor->job_type !== JobType::Supervisor) {
            throw ValidationException::withMessages([
                'employee' => 'Only a Supervisor can have supervisory duties revoked.',
            ]);
        }

        $blocks = $this->hasBlockingResponsibilities($supervisor);

        if ($blocks && $replacement === null) {
            throw ValidationException::withMessages([
                'replacement_employee_id' => 'Choose an active replacement Supervisor before revoking duties. Active DSPs or clients would otherwise be left without a Supervisor.',
            ]);
        }

        $jobTitle = $supervisor->job_title;
        $previousRole = $supervisor->user?->role->value ?? Role::Supervisor->value;

        return DB::transaction(function () use ($supervisor, $actor, $replacement, $blocks, $jobTitle, $previousRole): Employee {
            if ($blocks) {
                $replacement = $this->assertReplacement($supervisor, $replacement);

                Employee::query()
                    ->where('supervisor_id', $supervisor->id)
                    ->update(['supervisor_id' => $replacement->id]);

                Client::query()
                    ->where('supervisor_id', $supervisor->id)
                    ->update(['supervisor_id' => $replacement->id]);
            }

            $supervisor->job_type = JobType::Dsp;
            $supervisor->job_title = $jobTitle;
            $this->appendHistory($supervisor, [
                'action' => 'revoked',
                'previous_role' => $previousRole,
                'new_role' => Role::Dsp->value,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'at' => now()->toIso8601String(),
                'replacement_employee_id' => $replacement?->id,
                'replacement_name' => $replacement?->full_name,
            ]);
            $supervisor->save();

            $this->syncLoginRole($supervisor, JobType::Dsp);

            return $supervisor->fresh(['user']) ?? $supervisor;
        });
    }

    /**
     * @return Builder<Employee>
     */
    private function eligibleQuery(): Builder
    {
        return Employee::query()
            ->with('user')
            ->where('employment_status', EmploymentStatus::Active)
            ->where('job_type', JobType::Dsp)
            ->whereNotNull('user_id')
            ->whereHas('user', fn (Builder $query) => $query->where('role', Role::Dsp));
    }

    /**
     * @return Builder<Employee>
     */
    private function replacementQuery(Employee $except): Builder
    {
        return Employee::query()
            ->whereKeyNot($except->id)
            ->where('job_type', JobType::Supervisor)
            ->where('employment_status', EmploymentStatus::Active)
            ->whereHas('user', fn (Builder $query) => $query->where('role', Role::Supervisor));
    }

    private function assertReplacement(Employee $current, ?Employee $replacement): Employee
    {
        if ($replacement === null || $this->replacementQuery($current)->whereKey($replacement->id)->doesntExist()) {
            throw ValidationException::withMessages([
                'replacement_employee_id' => 'Select an active Supervisor who can take these responsibilities.',
            ]);
        }

        return $replacement->fresh() ?? $replacement;
    }

    private function syncLoginRole(Employee $employee, JobType $jobType): void
    {
        $user = $employee->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee does not have a linked login account.',
            ]);
        }

        $user->update([
            'role' => $jobType->toRole(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function appendHistory(Employee $employee, array $event): void
    {
        $history = $employee->role_change_history ?? [];
        $history[] = $event;
        $employee->role_change_history = $history;
    }
}
