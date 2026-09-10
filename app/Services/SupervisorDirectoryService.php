<?php

namespace App\Services;

use App\Enums\JobType;
use App\Models\Employee;
use App\Support\DirectoryPresenter;
use Illuminate\Pagination\LengthAwarePaginator;

class SupervisorDirectoryService
{
    public function __construct(private SupervisorOperationsService $operations) {}

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    public function paginate(?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return Employee::query()
            ->with('user')
            ->where('job_type', JobType::Supervisor)
            ->search($search)
            ->when($status !== null && $status !== '', fn ($query) => $query->where('employment_status', $status))
            ->withCount([
                'reports as assigned_dsp_count' => fn ($query) => $query->where('job_type', JobType::Dsp),
                'supervisedClients as assigned_client_count',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Employee $supervisor): array
    {
        $supervisor->load('user');

        return [
            'supervisor' => [
                ...DirectoryPresenter::employeeDetail($supervisor),
                'assigned_dsp_count' => $supervisor->reports()->where('job_type', JobType::Dsp)->count(),
                'assigned_client_count' => $supervisor->supervisedClients()->count(),
            ],
            'operations' => $this->operations->forSupervisor($supervisor),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Employee>  $supervisors
     * @return array<int, array<string, mixed>>
     */
    public function serializePage(LengthAwarePaginator $supervisors): array
    {
        $rows = [];

        foreach ($supervisors->getCollection() as $supervisor) {
            $board = $this->operations->forSupervisor($supervisor);

            $rows[] = [
                ...DirectoryPresenter::employeeSummary($supervisor),
                'assigned_dsp_count' => (int) ($supervisor->assigned_dsp_count ?? 0),
                'assigned_client_count' => (int) ($supervisor->assigned_client_count ?? 0),
                'visits_today' => count($board['today_visits']),
                'active_visits' => count($board['active_visits']),
                'open_exceptions' => count($board['exceptions']['open']),
            ];
        }

        return $rows;
    }
}
