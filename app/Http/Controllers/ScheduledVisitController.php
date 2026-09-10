<?php

namespace App\Http\Controllers;

use App\Enums\ScheduledVisitStatus;
use App\Http\Requests\ScheduledVisitRequest;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ScheduledVisitService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledVisitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', ScheduledVisit::class);

        $filters = [
            'service_date' => $request->string('service_date')->trim()->value(),
            'client_id' => $request->string('client_id')->value(),
            'employee_id' => $request->string('employee_id')->value(),
            'status' => $request->string('status')->value(),
        ];

        $visits = ScheduledVisit::query()
            ->with(['client', 'employee', 'supervisor', 'shiftTemplate'])
            ->visibleTo($user)
            ->when(
                $filters['service_date'] !== '',
                fn ($query) => $query->whereDate('service_date', $filters['service_date']),
            )
            ->when(
                $filters['client_id'] !== '' && ctype_digit($filters['client_id']),
                fn ($query) => $query->where('client_id', (int) $filters['client_id']),
            )
            ->when(
                $filters['employee_id'] !== '' && ctype_digit($filters['employee_id']),
                fn ($query) => $query->where('employee_id', (int) $filters['employee_id']),
            )
            ->when(
                $filters['status'] !== '' && ScheduledVisitStatus::tryFrom($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('scheduled-visits/index', [
            'visits' => [
                'data' => $visits->getCollection()
                    ->map(fn (ScheduledVisit $visit): array => DirectoryPresenter::scheduledVisitSummary($visit))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $visits->currentPage(),
                    'last_page' => $visits->lastPage(),
                    'from' => $visits->firstItem(),
                    'to' => $visits->lastItem(),
                    'total' => $visits->total(),
                ],
                'links' => [
                    'prev' => $visits->previousPageUrl(),
                    'next' => $visits->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'clients' => DirectoryPresenter::clientFilterOptions($user),
            'dsps' => DirectoryPresenter::dspFilterOptions($user),
            'can' => [
                'create' => $user->can('create', ScheduledVisit::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ScheduledVisit::class);

        $user = $request->user();
        abort_unless($user !== null, 401);

        return Inertia::render('scheduled-visits/create', $this->formOptions($user));
    }

    public function store(ScheduledVisitRequest $request, ScheduledVisitService $visits): RedirectResponse
    {
        $visit = $visits->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled visit created.')]);

        return redirect()->route('scheduled-visits.show', $visit);
    }

    public function show(Request $request, ScheduledVisit $scheduledVisit): Response
    {
        $this->authorize('view', $scheduledVisit);

        $scheduledVisit->load(['client', 'employee', 'supervisor', 'shiftTemplate']);

        return Inertia::render('scheduled-visits/show', [
            'visit' => DirectoryPresenter::scheduledVisitDetail($scheduledVisit),
            'can' => [
                'update' => $request->user()?->can('update', $scheduledVisit) ?? false,
            ],
        ]);
    }

    public function edit(Request $request, ScheduledVisit $scheduledVisit): Response
    {
        $this->authorize('update', $scheduledVisit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $scheduledVisit->load(['client', 'employee', 'supervisor', 'shiftTemplate']);

        return Inertia::render('scheduled-visits/edit', [
            'visit' => DirectoryPresenter::scheduledVisitDetail($scheduledVisit),
            ...$this->formOptions($user, $scheduledVisit),
        ]);
    }

    public function update(ScheduledVisitRequest $request, ScheduledVisit $scheduledVisit, ScheduledVisitService $visits): RedirectResponse
    {
        $visits->update($scheduledVisit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled visit updated.')]);

        return redirect()->route('scheduled-visits.show', $scheduledVisit);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user, ?ScheduledVisit $visit = null): array
    {
        return [
            'clients' => DirectoryPresenter::schedulingClientOptions($user),
            'dsps' => DirectoryPresenter::schedulingDspOptions($user, $visit?->employee),
            'supervisors' => DirectoryPresenter::supervisorOptions(),
            'shiftTemplates' => DirectoryPresenter::shiftTemplateOptions($visit?->shiftTemplate),
        ];
    }
}
