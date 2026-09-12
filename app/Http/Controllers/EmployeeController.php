<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeStatusRequest;
use App\Models\Employee;
use App\Services\EmployeeManagementService;
use App\Services\ProfileCompletionService;
use App\Services\ProfilePhotoService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request, ProfilePhotoService $photos): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', Employee::class);

        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'employment_status' => $request->string('employment_status')->value(),
            'job_type' => $request->string('job_type')->value(),
        ];

        $employees = Employee::query()
            ->with('supervisor')
            ->visibleTo($user)
            ->search($filters['search'] !== '' ? $filters['search'] : null)
            ->when(
                $filters['employment_status'] !== '' && EmploymentStatus::tryFrom($filters['employment_status']),
                fn ($query) => $query->where('employment_status', $filters['employment_status']),
            )
            ->when(
                $filters['job_type'] !== '' && JobType::tryFrom($filters['job_type']),
                fn ($query) => $query->where('job_type', $filters['job_type']),
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => [
                'data' => $employees->getCollection()
                    ->map(fn (Employee $employee): array => DirectoryPresenter::employeeSummary($employee, $photos))
                    ->values()
                    ->all(),
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
            'can' => [
                'create' => $user->can('create', Employee::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('employees/create', [
            'supervisors' => DirectoryPresenter::supervisorOptions(),
            'linkableUsers' => DirectoryPresenter::linkableUsers(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, EmployeeManagementService $employees): RedirectResponse
    {
        $employee = $employees->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee created.')]);

        return redirect()->route('employees.show', $employee);
    }

    public function show(
        Request $request,
        Employee $employee,
        ProfileCompletionService $completion,
        ProfilePhotoService $photos,
    ): Response {
        $this->authorize('view', $employee);

        $user = $request->user();
        $includeSensitive = $user?->can('viewSensitive', $employee) ?? false;

        $employee->load([
            'supervisor',
            'user',
            'credentials' => fn ($query) => $query->orderByDesc('expires_on')->orderBy('name'),
            'trainings' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('title'),
            'educations',
            'personalReferences',
            'workHistories',
            'weeklyAvailabilities',
            'securityIncidents' => fn ($query) => $includeSensitive ? $query : $query->whereRaw('1 = 0'),
        ]);

        return Inertia::render('employees/show', [
            'employee' => DirectoryPresenter::employeeDetail($employee, $includeSensitive, $photos),
            'credentials' => DirectoryPresenter::credentials($employee->credentials),
            'trainings' => DirectoryPresenter::trainings($employee->trainings),
            'activity' => DirectoryPresenter::employeeActivity($employee),
            'profile_completion' => $completion->forEmployee($employee),
            'can' => [
                'update' => $user?->can('update', $employee) ?? false,
                'view_sensitive' => $includeSensitive,
            ],
        ]);
    }

    public function edit(Employee $employee, ProfilePhotoService $photos): Response
    {
        $this->authorize('update', $employee);

        $includeSensitive = request()->user()?->can('viewSensitive', $employee) ?? false;

        $employee->load([
            'supervisor',
            'user',
            'educations',
            'personalReferences',
            'workHistories',
            'weeklyAvailabilities',
            'securityIncidents',
        ]);

        return Inertia::render('employees/edit', [
            'employee' => DirectoryPresenter::employeeDetail($employee, $includeSensitive, $photos),
            'supervisors' => DirectoryPresenter::supervisorOptions(),
            'linkableUsers' => DirectoryPresenter::linkableUsers(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, EmployeeManagementService $employees): RedirectResponse
    {
        $employees->update($employee, $request->validated(), array_keys($request->all()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return redirect()->route('employees.show', $employee);
    }

    public function updateStatus(UpdateEmployeeStatusRequest $request, Employee $employee, EmployeeManagementService $employees): RedirectResponse
    {
        $status = EmploymentStatus::from($request->validated('employment_status'));
        $employees->updateStatus($employee, $status, $request->validated('terminated_on'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employment status updated.')]);

        return redirect()->route('employees.show', $employee);
    }
}
