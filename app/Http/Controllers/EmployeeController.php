<?php

namespace App\Http\Controllers;

use App\Enums\CredentialType;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateEmployeeStatusRequest;
use App\Models\Employee;
use App\Services\ComplianceEligibilityService;
use App\Services\EmployeeAdminHistoryService;
use App\Services\EmployeeLifecycleService;
use App\Services\EmployeeManagementService;
use App\Services\ProfileCompletionService;
use App\Services\ProfilePhotoService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\JsonResponse;
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

        $statusFilter = EmploymentStatus::tryFrom($filters['employment_status']);

        $employees = Employee::query()
            ->with('supervisor')
            ->visibleTo($user)
            ->search($filters['search'] !== '' ? $filters['search'] : null)
            ->when(
                $statusFilter !== null,
                fn ($query) => $query->where('employment_status', $statusFilter),
                fn ($query) => $query->operational(),
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
        ComplianceEligibilityService $eligibility,
        EmployeeLifecycleService $lifecycle,
        EmployeeAdminHistoryService $adminHistory,
    ): Response {
        $this->authorize('view', $employee);

        $user = $request->user();
        $includeSensitive = $user?->can('viewSensitive', $employee) ?? false;
        $canOverrideCompliance = $user?->isAdmin() ?? false;
        $canManageLifecycle = $user?->can('manageLifecycle', $employee) ?? false;

        $employee->load([
            'supervisor',
            'user',
            'statusChangedBy',
            'credentials' => fn ($query) => $query->orderByDesc('expires_on')->orderBy('name'),
            'trainings' => fn ($query) => $query->orderByDesc('completed_on')->orderBy('title'),
            'educations',
            'personalReferences',
            'workHistories',
            'weeklyAvailabilities',
            'securityIncidents' => fn ($query) => $includeSensitive ? $query : $query->whereRaw('1 = 0'),
        ]);

        $compliance = $eligibility->evaluate($employee);

        return Inertia::render('employees/show', [
            'employee' => DirectoryPresenter::employeeDetail($employee, $includeSensitive, $photos),
            'credentials' => DirectoryPresenter::credentials($employee->credentials),
            'trainings' => DirectoryPresenter::trainings($employee->trainings),
            'activity' => DirectoryPresenter::employeeActivity($employee),
            'admin_history' => $canManageLifecycle ? $adminHistory->serializeForEmployee($employee) : [],
            'profile_completion' => $completion->forEmployee($employee),
            'compliance_eligibility' => $compliance,
            'lifecycle_options' => EmploymentStatus::options(),
            'lifecycle_previews' => $canManageLifecycle
                ? [
                    'active' => $lifecycle->impactPreview($employee, EmploymentStatus::Active),
                    'suspended' => $lifecycle->impactPreview($employee, EmploymentStatus::Suspended),
                    'employment_ended' => $lifecycle->impactPreview($employee, EmploymentStatus::EmploymentEnded),
                    'archived' => ($user?->can('archive', $employee) ?? false)
                        ? $lifecycle->impactPreview($employee, EmploymentStatus::Archived)
                        : null,
                ]
                : null,
            'credential_type_options' => collect(CredentialType::cases())
                ->map(fn (CredentialType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->all(),
            'can' => [
                'update' => $user?->can('update', $employee) ?? false,
                'manage_lifecycle' => $canManageLifecycle,
                'archive' => $user?->can('archive', $employee) ?? false,
                'view_sensitive' => $includeSensitive,
                'override_compliance' => $canOverrideCompliance,
                'verify_compliance' => $user?->isAdmin() ?? false,
                'submit_compliance_evidence' => ($user?->isAdmin() ?? false)
                    || ($user?->isDsp() && $user->employee?->is($employee)),
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

    public function updateStatus(
        UpdateEmployeeStatusRequest $request,
        Employee $employee,
        EmployeeLifecycleService $lifecycle,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $lifecycle->transition($employee, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee lifecycle updated.')]);

        return redirect()->route('employees.show', $employee);
    }

    public function lifecyclePreview(
        Request $request,
        Employee $employee,
        EmployeeLifecycleService $lifecycle,
    ): JsonResponse {
        $this->authorize('manageLifecycle', $employee);

        $status = EmploymentStatus::tryFrom((string) $request->query('status', ''));
        abort_unless($status !== null, 422);

        if ($status === EmploymentStatus::Archived) {
            $this->authorize('archive', $employee);
        }

        return response()->json(
            $lifecycle->impactPreview(
                $employee,
                $status,
                $request->query('effective_on'),
            ),
        );
    }
}
