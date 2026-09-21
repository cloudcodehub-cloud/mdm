<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Http\Requests\PromoteSupervisorRequest;
use App\Http\Requests\ReassignSupervisorCaseloadRequest;
use App\Http\Requests\ReassignSupervisorTeamRequest;
use App\Http\Requests\RevokeSupervisorRequest;
use App\Models\Employee;
use App\Services\SupervisorDirectoryService;
use App\Services\SupervisorManagementService;
use Illuminate\Http\RedirectResponse;
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
            'can' => [
                'manage' => $user->can('manageSupervisors', Employee::class),
            ],
        ]);
    }

    public function create(Request $request, SupervisorManagementService $management): Response
    {
        $this->authorize('manageSupervisors', Employee::class);

        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'employee_id' => $request->string('employee_id')->value(),
        ];

        $employees = $management->paginateEligible(
            $filters['search'] !== '' ? $filters['search'] : null,
        );

        $selectedId = ctype_digit($filters['employee_id']) ? (int) $filters['employee_id'] : 0;
        $selected = $selectedId > 0 ? $management->findEligible($selectedId) : null;

        return Inertia::render('supervisors/create', [
            'employees' => [
                'data' => $management->serializeEligiblePage($employees),
                'meta' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                    'total' => $employees->total(),
                ],
                'links' => [
                    'prev' => $employees->previousPageUrl(),
                    'next' => $employees->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'selected' => $selected !== null ? $management->serializeCandidate($selected) : null,
        ]);
    }

    public function store(PromoteSupervisorRequest $request, SupervisorManagementService $management): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 401);
        $employee = $management->promote($request->employee(), $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is now a Supervisor. Job title was left unchanged.', [
                'name' => $employee->full_name,
            ]),
        ]);

        return redirect()->route('supervisors.show', $employee);
    }

    public function show(
        Request $request,
        Employee $employee,
        SupervisorDirectoryService $directory,
        SupervisorManagementService $management,
    ): Response {
        $this->authorize('viewSupervisorDirectory', Employee::class);
        abort_unless($employee->job_type === JobType::Supervisor, 404);

        $user = $request->user();
        abort_unless($user !== null, 401);
        $canManage = $user->can('manageSupervisors', Employee::class);
        $detail = $directory->detail($employee);

        return Inertia::render('supervisors/show', [
            'supervisor' => $detail['supervisor'],
            'operations' => $detail['operations'],
            'responsibilities' => $canManage ? $management->responsibilities($employee) : null,
            'replacements' => $canManage ? $management->replacementOptions($employee) : [],
            'role_change_history' => $canManage ? ($employee->role_change_history ?? []) : [],
            'can' => [
                'manage' => $canManage,
            ],
        ]);
    }

    public function reassignTeam(
        ReassignSupervisorTeamRequest $request,
        Employee $employee,
        SupervisorManagementService $management,
    ): RedirectResponse {
        abort_unless($employee->job_type === JobType::Supervisor, 404);
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $management->reassignTeam(
            $employee,
            $request->replacement(),
            $request->employeeIds(),
            $actor,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('DSP team members were transferred. Historical visits were not changed.'),
        ]);

        return redirect()->route('supervisors.show', $employee);
    }

    public function reassignCaseload(
        ReassignSupervisorCaseloadRequest $request,
        Employee $employee,
        SupervisorManagementService $management,
    ): RedirectResponse {
        abort_unless($employee->job_type === JobType::Supervisor, 404);
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $management->reassignCaseload(
            $employee,
            $request->replacement(),
            $request->clientIds(),
            $actor,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Client caseload was transferred. Historical visits were not changed.'),
        ]);

        return redirect()->route('supervisors.show', $employee);
    }

    public function revoke(
        RevokeSupervisorRequest $request,
        Employee $employee,
        SupervisorManagementService $management,
    ): RedirectResponse {
        abort_unless($employee->job_type === JobType::Supervisor, 404);
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $management->revoke($employee, $actor, $request->replacement());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name no longer has Supervisor duties. The employee record and job title remain in place.', [
                'name' => $employee->full_name,
            ]),
        ]);

        return redirect()->route('supervisors.index');
    }
}
