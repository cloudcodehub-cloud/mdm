<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\UpdateClientStatusRequest;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Services\CareOverviewService;
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

        $attention->syncClient($client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return redirect()->route('clients.setup.edit', $client);
    }

    public function show(Request $request, Client $client, CareOverviewService $overview, ProfileCompletionService $completion): Response
    {
        $this->authorize('view', $client);

        $user = $request->user();
        $user?->loadMissing('employee');
        $isDsp = $user?->isDsp() ?? false;
        $dspId = $isDsp ? $user?->employee?->id : null;

        $client->load([
            'supervisor.user',
            'carePlans.taskTemplates',
            'careServices',
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
        $carePlans = $isDsp
            ? $client->carePlans->filter(fn (CarePlan $plan): bool => $plan->isCurrentlyActive())->values()
            : $client->carePlans;
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

        $supervisor = $client->supervisor;
        $supervisorUser = $supervisor?->user;
        $canManageCarePlan = $user?->can('manageCarePlan', $client) ?? false;
        $supervisorName = $supervisor !== null ? $supervisor->full_name : $supervisorUser?->name;

        return Inertia::render('clients/show', [
            'client' => DirectoryPresenter::clientDetail($client),
            'profile_completion' => $completion->forClient($client),
            'authorizations' => DirectoryPresenter::authorizations($authorizations),
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
            'care_overview' => $user !== null ? $overview->forClient($client, $user) : [
                'today' => [],
                'upcoming' => [],
                'history' => [],
                'services' => [],
            ],
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
            'can' => [
                'update' => $user?->can('update', $client) ?? false,
                'manageAssignments' => $user?->can('create', ClientDspAssignment::class) ?? false,
                'manageCarePlan' => $canManageCarePlan,
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
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['profile_photo'], $data['remove_photo']);
        $client->update($data);

        if ($request->boolean('remove_photo')) {
            $photos->removeClient($client);
        }

        if ($request->file('profile_photo')) {
            $photos->storeClient($client, $request->file('profile_photo'));
        }

        $attention->syncClient($client->fresh() ?? $client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return redirect()->route('clients.show', $client);
    }

    public function updateStatus(UpdateClientStatusRequest $request, Client $client): RedirectResponse
    {
        $client->update(['status' => $request->validated('status')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client status updated.')]);

        return redirect()->route('clients.show', $client);
    }
}
