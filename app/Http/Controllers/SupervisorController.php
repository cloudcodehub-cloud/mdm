<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use App\Services\SupervisorDirectoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorController extends Controller
{
    public function index(Request $request, SupervisorDirectoryService $directory): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewSupervisorDirectory', Employee::class);

        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'employment_status' => $request->string('employment_status')->value(),
        ];

        $status = $filters['employment_status'] !== '' && EmploymentStatus::tryFrom($filters['employment_status'])
            ? $filters['employment_status']
            : null;

        $supervisors = $directory->paginate(
            $filters['search'] !== '' ? $filters['search'] : null,
            $status,
        );

        return Inertia::render('supervisors/index', [
            'supervisors' => [
                'data' => $directory->serializePage($supervisors),
                'meta' => [
                    'current_page' => $supervisors->currentPage(),
                    'last_page' => $supervisors->lastPage(),
                    'from' => $supervisors->firstItem(),
                    'to' => $supervisors->lastItem(),
                    'total' => $supervisors->total(),
                ],
                'links' => [
                    'prev' => $supervisors->previousPageUrl(),
                    'next' => $supervisors->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Employee $employee, SupervisorDirectoryService $directory): Response
    {
        $this->authorize('viewSupervisorDirectory', Employee::class);
        abort_unless($employee->job_type === JobType::Supervisor, 404);

        $detail = $directory->detail($employee);

        return Inertia::render('supervisors/show', [
            'supervisor' => $detail['supervisor'],
            'operations' => $detail['operations'],
        ]);
    }
}
