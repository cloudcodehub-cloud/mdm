<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Visit;

/**
 * MDM 03 Archive Pending orchestration.
 *
 * The Admin archive request is accepted once (EmployeeLifecycleService / ClientLifecycleService);
 * this service finishes it automatically after the blocking dependency ends, so the Admin never
 * has to return and repeat. It adds no states and no approval step.
 */
class ArchivePendingService
{
    public function __construct(
        private EmployeeLifecycleService $employees,
        private ClientLifecycleService $clients,
    ) {}

    /**
     * Visit clock-out hook: the Client and DSP of a just-completed visit may finalize.
     *
     * @return int number of records that became Archived
     */
    public function onVisitCompleted(Visit $visit): int
    {
        $finalized = 0;

        $client = Client::query()->find($visit->client_id);

        if ($client !== null && $client->isArchivePending() && $this->clients->finalizeArchivePending($client)) {
            $finalized++;
        }

        $employee = $visit->employee_id !== null ? Employee::query()->find($visit->employee_id) : null;

        if ($employee !== null && $employee->isArchivePending() && $this->employees->finalizeArchivePending($employee)) {
            $finalized++;
        }

        // A finalized client/DSP may have been the last live dependency of a pending Supervisor.
        return $finalized + $this->sweepSupervisors();
    }

    /**
     * Supervisor team/caseload changed (reassignment, revocation, supervisor edit).
     */
    public function finalizeSupervisorIfCleared(Employee $employee): bool
    {
        $fresh = Employee::query()->find($employee->id);

        if ($fresh === null || ! $fresh->isArchivePending()) {
            return false;
        }

        return $this->employees->finalizeArchivePending($fresh);
    }

    /**
     * Finalize every pending Supervisor whose caseload/team is now covered.
     */
    public function sweepSupervisors(): int
    {
        $finalized = 0;

        Employee::query()
            ->where('employment_status', EmploymentStatus::ArchivePending)
            ->where('job_type', JobType::Supervisor)
            ->orderBy('id')
            ->get()
            ->each(function (Employee $supervisor) use (&$finalized): void {
                if ($this->employees->finalizeArchivePending($supervisor)) {
                    $finalized++;
                }
            });

        return $finalized;
    }

    /**
     * Scheduled safety net: finalize everything whose blockers have cleared.
     * Order matters — clients and DSPs first, then Supervisors that depended on them.
     *
     * @return array{clients: int, employees: int, supervisors: int}
     */
    public function sweep(): array
    {
        $clients = 0;

        Client::query()
            ->where('status', ClientStatus::ArchivePending)
            ->orderBy('id')
            ->get()
            ->each(function (Client $client) use (&$clients): void {
                if ($this->clients->finalizeArchivePending($client)) {
                    $clients++;
                }
            });

        $employees = 0;

        Employee::query()
            ->where('employment_status', EmploymentStatus::ArchivePending)
            ->where('job_type', '!=', JobType::Supervisor)
            ->orderBy('id')
            ->get()
            ->each(function (Employee $employee) use (&$employees): void {
                if ($this->employees->finalizeArchivePending($employee)) {
                    $employees++;
                }
            });

        return [
            'clients' => $clients,
            'employees' => $employees,
            'supervisors' => $this->sweepSupervisors(),
        ];
    }
}
