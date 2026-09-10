<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\UpdateClientStatusRequest;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Support\DirectoryPresenter;
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

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['client_number'] = filled($data['client_number'] ?? null)
            ? $data['client_number']
            : Client::nextClientNumber();

        $client = Client::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return redirect()->route('clients.show', $client);
    }

    public function show(Request $request, Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load([
            'supervisor',
            'authorizations' => fn ($query) => $query->orderByDesc('starts_on'),
            'carePlans.taskTemplates',
            'dspAssignments.employee',
            'scheduledVisits.employee',
            'scheduledVisits.supervisor',
            'scheduledVisits.shiftTemplate',
        ]);

        $visits = $client->scheduledVisits
            ->sortByDesc(fn ($visit) => $visit->service_date->toDateString())
            ->values();
        $assignments = $client->dspAssignments
            ->sortByDesc(fn ($assignment) => $assignment->started_on->toDateString())
            ->values();

        return Inertia::render('clients/show', [
            'client' => DirectoryPresenter::clientDetail($client),
            'authorizations' => DirectoryPresenter::authorizations($client->authorizations),
            'carePlans' => DirectoryPresenter::carePlans($client->carePlans),
            'assignments' => DirectoryPresenter::assignments($assignments),
            'scheduledVisits' => DirectoryPresenter::scheduledVisits($visits),
            'dspOptions' => DirectoryPresenter::dspOptions(),
            'can' => [
                'update' => $request->user()?->can('update', $client) ?? false,
                'manageAssignments' => $request->user()?->can('create', ClientDspAssignment::class) ?? false,
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

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

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
