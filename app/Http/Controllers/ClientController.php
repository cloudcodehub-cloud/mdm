<?php

namespace App\Http\Controllers;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\ClientContactRole;
use App\Enums\ClientStatus;
use App\Enums\CriticalAlertCategory;
use App\Enums\DspClientAccessLevel;
use App\Enums\ScheduledVisitStatus;
use Illuminate\Support\Str;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\UpdateClientStatusRequest;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\ScheduledVisit;
use App\Services\CareOverviewService;
use App\Services\CarePlanRevisionService;
use App\Services\AuthorizationUsageService;
use App\Services\CarePlanReviewService;
use App\Services\ClientActiveVisitGuard;
use App\Services\ClientAdminHistoryService;
use App\Services\ClientContactService;
use App\Services\ClientLifecycleService;
use App\Services\ClientNoteCorrectionService;
use App\Services\ClientServiceLocationService;
use App\Services\CriticalAlertService;
use App\Services\DspClientAccessService;
use App\Services\ProfileAttentionService;
use App\Services\ProfileCompletionService;
use App\Services\ProfilePhotoService;
use App\Services\SettingsService;
use App\Support\CareServicePresenter;
use App\Support\DirectoryPresenter;
use App\Support\TaskCatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', Client::class);

        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => $request->string('status')->value(),
            'supervisor_id' => $request->string('supervisor_id')->value(),
        ];

        $clients = Client::query()
            ->with(['supervisor', 'dspAssignments'])
            ->visibleTo($user)
            ->search($filters['search'] !== '' ? $filters['search'] : null)
            ->when(
                $filters['status'] !== '' && ClientStatus::tryFrom($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['supervisor_id'] !== '' && ctype_digit($filters['supervisor_id']),
                fn ($query) => $query->where('supervisor_id', (int) $filters['supervisor_id']),
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('clients/index', [
            'clients' => [
                'data' => $clients->getCollection()
                    ->map(fn (Client $client): array => DirectoryPresenter::clientSummary($client))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'from' => $clients->firstItem(),
                    'to' => $clients->lastItem(),
                    'total' => $clients->total(),
                ],
                'links' => [
                    'prev' => $clients->previousPageUrl(),
                    'next' => $clients->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'supervisors' => DirectoryPresenter::supervisorOptions(),
            'can' => [
                'create' => $user->can('create', Client::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('clients/create', [
            'supervisors' => DirectoryPresenter::supervisorOptions(),
        ]);
    }

    public function store(
        StoreClientRequest $request,
        ProfilePhotoService $photos,
        ProfileAttentionService $attention,
    ): RedirectResponse {
        $data = $request->validated();
        $data['client_number'] = filled($data['client_number'] ?? null)
            ? $data['client_number']
            : Client::nextClientNumber();

        unset($data['profile_photo'], $data['remove_photo']);
        $client = Client::query()->create($data);

        if ($request->file('profile_photo')) {
            $photos->storeClient($client, $request->file('profile_photo'));
        }

        $this->syncLegacyContactAndLocation($client, $request->user());

        $attention->syncClient($client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return redirect()->route('clients.setup.edit', $client);
    }

    public function show(
        Request $request,
        Client $client,
        CareOverviewService $overview,
        ProfileCompletionService $completion,
        DspClientAccessService $dspAccess,
        CriticalAlertService $criticalAlerts,
        AuthorizationUsageService $authorizationUsage,
        CarePlanReviewService $carePlanReviews,
        ClientLifecycleService $lifecycle,
        ClientContactService $contacts,
        ClientServiceLocationService $serviceLocations,
        ClientAdminHistoryService $adminHistory,
    ): Response {
        $this->authorize('view', $client);

        $user = $request->user();
        $user?->loadMissing('employee');
        $isDsp = $user?->isDsp() ?? false;
        $dspId = $isDsp ? $user?->employee?->id : null;
        $accessLevel = $isDsp && $user !== null
            ? $dspAccess->levelFor($user, $client)
            : DspClientAccessLevel::Full;
        $fullAccess = ! $isDsp || $accessLevel === DspClientAccessLevel::Full;
        $olderHistory = $fullAccess && $request->string('history_scope')->toString() === 'older';

        $client->load([
            'supervisor.user',
            'carePlans.taskTemplates',
            'careServices',
            'contacts',
            'serviceLocations',
            'scheduledVisits' => function ($query) use ($dspId): void {
                $query->with(['employee', 'supervisor', 'shiftTemplate', 'visit']);

                if ($dspId !== null) {
                    $query->where('employee_id', $dspId);
                }
            },
        ]);

        if (! $isDsp) {
            $client->load([
                'authorizations' => fn ($query) => $query->orderByDesc('starts_on'),
                'dspAssignments.employee',
            ]);
        }

        $visits = $client->scheduledVisits
            ->sortByDesc(fn ($visit) => $visit->service_date->toDateString())
            ->values();
        $assignments = $isDsp
            ? collect()
            : $client->dspAssignments
                ->sortByDesc(fn ($assignment) => $assignment->started_on->toDateString())
                ->values();
        app(CarePlanRevisionService::class)->effectivePlanFor($client);

        $client->unsetRelation('carePlans');
        $client->load(['carePlans.taskTemplates']);

        $carePlans = (! $isDsp || $fullAccess)
            ? ($isDsp
                ? $client->carePlans->filter(fn (CarePlan $plan): bool => $plan->isCurrentlyActive())->values()
                : $client->carePlans
                    ->sortByDesc(fn (CarePlan $plan): array => [
                        $plan->status->value === 'pending' ? 2 : ($plan->status->value === 'active' ? 1 : 0),
                        $plan->revision_number,
                        $plan->id,
                    ])
                    ->values())
            : collect();
        $authorizations = $isDsp ? collect() : $client->authorizations;

        $today = app(SettingsService::class)->today();
        $todayVisit = $visits->first(function ($visit) use ($today, $user): bool {
            if ($visit->service_date->toDateString() !== $today) {
                return false;
            }

            if ($user?->isDsp()) {
                return $visit->employee_id === $user->employee?->id;
            }

            return true;
        });

        $nextPreparationVisit = null;

        if ($isDsp) {
            $nextPreparationVisit = $visits
                ->filter(fn (ScheduledVisit $visit): bool => $visit->status === ScheduledVisitStatus::Scheduled)
                ->sortBy(function (ScheduledVisit $visit): int {
                    try {
                        return $visit->startsAtOn()->getTimestamp();
                    } catch (\RuntimeException) {
                        return PHP_INT_MAX;
                    }
                })
                ->first();
        }

        $supervisor = $client->supervisor;
        $supervisorUser = $supervisor?->user;
        $canManageCarePlan = $user?->can('manageCarePlan', $client) ?? false;
        $supervisorName = $supervisor !== null ? $supervisor->full_name : $supervisorUser?->name;

        $clientPayload = $fullAccess
            ? DirectoryPresenter::clientDetail($client)
            : DirectoryPresenter::clientPreparationDetail($client, $nextPreparationVisit);

        // Archive Pending admin detail (reason/requester) is not for DSPs.
        if ($isDsp && isset($clientPayload['archive_pending'])) {
            $clientPayload['archive_pending'] = null;
        }

        $careOverview = $user === null
            ? [
                'today' => [],
                'upcoming' => [],
                'history' => [],
                'services' => [],
                'history_scope' => 'recent',
                'history_days' => 30,
                'can_view_older_history' => false,
            ]
            : $overview->forClient(
                $client,
                $user,
                // Upcoming/today care templates remain visible for preparation;
                // cross-DSP history stays locked until the full-access window.
                includeFullCare: true,
                includeHistory: $fullAccess,
                olderHistory: $olderHistory,
            );

        return Inertia::render('clients/show', [
            'client' => $clientPayload,
            'dsp_access' => $isDsp ? $dspAccess->serialize($accessLevel) : null,
            'profile_completion' => $completion->forClient($client),
            'authorizations' => DirectoryPresenter::authorizations($authorizations),
            'active_visit_change_warning' => $isDsp
                ? null
                : app(ClientActiveVisitGuard::class)->warningPayload($client),
            'authorization_units' => collect(AuthorizationUnit::cases())
                ->map(fn (AuthorizationUnit $unit): array => [
                    'value' => $unit->value,
                    'label' => Str::headline($unit->value),
                ])
                ->values()
                ->all(),
            'authorization_statuses' => collect(AuthorizationStatus::cases())
                ->map(fn (AuthorizationStatus $status): array => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                ])
                ->values()
                ->all(),
            'carePlans' => DirectoryPresenter::carePlans($carePlans),
            'assignments' => DirectoryPresenter::assignments($assignments),
            'scheduledVisits' => DirectoryPresenter::scheduledVisits($visits),
            'today_visit' => $todayVisit === null ? null : [
                ...DirectoryPresenter::scheduledVisitSummary($todayVisit),
                'active_visit_id' => $todayVisit->visit?->id,
                'can_start' => ($user?->can('clockIn', $todayVisit) ?? false)
                    && $todayVisit->isEligibleToStart(),
                'is_completed' => $todayVisit->status->value === 'completed'
                    || $todayVisit->visit?->status?->value === 'completed',
            ],
            'care_overview' => $careOverview,
            'care_services' => CareServicePresenter::options($client->careServices),
            'task_catalog' => $canManageCarePlan ? TaskCatalogPresenter::payload() : null,
            'supervisor_contact' => $supervisorUser === null ? null : [
                'user_id' => $supervisorUser->id,
                'name' => $supervisorName ?? $supervisorUser->name,
                'available' => $supervisorUser->canMessage() && $supervisorUser->id !== $user?->id,
            ],
            'dspOptions' => ($user?->can('create', ClientDspAssignment::class) ?? false)
                ? DirectoryPresenter::dspOptions()
                : [],
            'critical_alerts' => $criticalAlerts->serializeForClient(
                $client,
                includeInactive: ($user?->can('manageCriticalAlerts', $client) ?? false),
            ),
            'critical_alert_categories' => CriticalAlertCategory::options(),
            'contacts' => $user === null
                ? []
                : $contacts->serializeForViewer($client, $user, $fullAccess),
            'contact_roles' => ClientContactRole::options(),
            'service_locations' => $serviceLocations->serializeForClient(
                $client,
                includeInactive: ($user?->can('manageServiceLocations', $client) ?? false),
            ),
            'admin_history' => ($user?->can('viewAdminHistory', $client) ?? false)
                ? $adminHistory->serializeForClient($client)
                : [],
            'authorization_usage' => $isDsp
                ? []
                : $authorizationUsage->summarizeMany($authorizations),
            'care_plan_review_warnings' => $carePlanReviews->warningsForClientPlans($carePlans),
            'lifecycle_options' => ClientStatus::options(),
            'lifecycle_previews' => ($user?->can('manageLifecycle', $client) ?? false)
                ? [
                    'paused' => $lifecycle->impactPreview($client, ClientStatus::Paused),
                    'discharged' => $lifecycle->impactPreview($client, ClientStatus::Discharged),
                    'archived' => ($user?->can('archive', $client) ?? false)
                        ? $lifecycle->impactPreview($client, ClientStatus::Archived)
                        : null,
                    'active' => $lifecycle->impactPreview($client, ClientStatus::Active),
                ]
                : null,
            'can' => [
                'update' => $user?->can('update', $client) ?? false,
                'manageAssignments' => $user?->can('create', ClientDspAssignment::class) ?? false,
                'manageCarePlan' => $canManageCarePlan,
                'manageCriticalAlerts' => $user?->can('manageCriticalAlerts', $client) ?? false,
                'manageContacts' => $user?->can('manageContacts', $client) ?? false,
                'manageServiceLocations' => $user?->can('manageServiceLocations', $client) ?? false,
                'manageAuthorizations' => $user?->can('create', ClientAuthorization::class) ?? false,
                'viewAdminHistory' => $user?->can('viewAdminHistory', $client) ?? false,
                'correctCarePlanEffectiveDate' => $user?->isAdmin() ?? false,
                'manageLifecycle' => $user?->can('manageLifecycle', $client) ?? false,
                'archive' => $user?->can('archive', $client) ?? false,
                'schedule_visit' => ($user?->can('create', ScheduledVisit::class) ?? false)
                    && $client->status->allowsFutureScheduling(),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        $client->load('supervisor');

        return Inertia::render('clients/edit', [
            'client' => DirectoryPresenter::clientDetail($client),
            'supervisors' => DirectoryPresenter::supervisorOptions(),
        ]);
    }

    public function update(
        UpdateClientRequest $request,
        Client $client,
        ProfilePhotoService $photos,
        ProfileAttentionService $attention,
        ClientNoteCorrectionService $noteCorrections,
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['profile_photo'], $data['remove_photo']);

        // Archive / Archive Pending changes go through ClientLifecycleService only (reason, audit, pending flow).
        $requestedStatus = ClientStatus::tryFrom((string) ($data['status'] ?? ''));

        if ($client->isArchivePending()
            || in_array($requestedStatus, [ClientStatus::Archived, ClientStatus::ArchivePending], true)) {
            unset($data['status']);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $prepared = $noteCorrections->prepareUpdate($client, $data);
        $data = $prepared['data'];

        $client->update($data);

        if ($prepared['history'] !== null) {
            $noteCorrections->recordAfterSave($client->fresh() ?? $client, $user, $prepared['history']);
        }

        // MDM 03: moving a client to another Supervisor may clear a pending Supervisor archive.
        app(\App\Services\ArchivePendingService::class)->sweepSupervisors();

        if ($request->boolean('remove_photo')) {
            $photos->removeClient($client);
        }

        if ($request->file('profile_photo')) {
            $photos->storeClient($client, $request->file('profile_photo'));
        }

        $this->syncLegacyContactAndLocation($client->fresh() ?? $client, $user);

        $attention->syncClient($client->fresh() ?? $client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return redirect()->route('clients.show', $client);
    }

    private function syncLegacyContactAndLocation(Client $client, ?\App\Models\User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $contacts = app(ClientContactService::class);
        $locations = app(ClientServiceLocationService::class);

        if (filled($client->emergency_contact_name) && ! $client->contacts()->where('role', ClientContactRole::Emergency)->exists()) {
            $contacts->create($client, $actor, [
                'role' => ClientContactRole::Emergency->value,
                'name' => $client->emergency_contact_name,
                'relationship' => $client->emergency_contact_relationship,
                'phone' => $client->emergency_contact_phone,
                'is_primary' => true,
            ]);
        } elseif (filled($client->emergency_contact_name)) {
            $primary = $client->contacts()
                ->where('role', ClientContactRole::Emergency)
                ->orderByDesc('is_primary')
                ->first();

            if ($primary !== null && (
                $primary->name !== $client->emergency_contact_name
                || $primary->relationship !== $client->emergency_contact_relationship
                || $primary->phone !== $client->emergency_contact_phone
            )) {
                $contacts->update($primary, $actor, [
                    'name' => $client->emergency_contact_name,
                    'relationship' => $client->emergency_contact_relationship,
                    'phone' => $client->emergency_contact_phone,
                    'is_primary' => true,
                ]);
            }
        }

        if (filled($client->address_line_1) && ! $client->serviceLocations()->exists()) {
            $locations->create($client, $actor, [
                'label' => 'Primary',
                'address_line_1' => $client->address_line_1,
                'address_line_2' => $client->address_line_2,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postal_code,
                'is_primary' => true,
            ]);
        }
    }

    public function updateStatus(
        UpdateClientStatusRequest $request,
        Client $client,
        ClientLifecycleService $lifecycle,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $lifecycle->transition($client, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client lifecycle updated.')]);

        return redirect()->route('clients.show', $client);
    }

    public function lifecyclePreview(
        Request $request,
        Client $client,
        ClientLifecycleService $lifecycle,
    ): \Illuminate\Http\JsonResponse {
        $this->authorize('manageLifecycle', $client);

        $status = ClientStatus::tryFrom((string) $request->query('status', ''));
        abort_unless($status !== null, 422);

        if ($status === ClientStatus::Archived) {
            $this->authorize('archive', $client);
        }

        return response()->json(
            $lifecycle->impactPreview(
                $client,
                $status,
                $request->query('effective_on'),
            ),
        );
    }
}
