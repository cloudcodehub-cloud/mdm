<?php

namespace App\Http\Controllers;

use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Http\Requests\ScheduledVisitRequest;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ScheduledVisitService;
use App\Services\VisitCarePreviewService;
use App\Services\VisitClockInService;
use App\Support\CareServicePresenter;
use App\Support\DirectoryPresenter;
use Illuminate\Http\JsonResponse;
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
                'filter_dsps' => ! $user->isDsp(),
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

    public function carePreview(Request $request, VisitCarePreviewService $preview): JsonResponse
    {
        $this->authorize('create', ScheduledVisit::class);

        $clientId = (int) $request->integer('client_id');
        $serviceDate = $request->string('service_date')->trim()->value();
        $scheduledId = (int) $request->integer('scheduled_visit_id');

        abort_unless($clientId > 0 && $serviceDate !== '', 422);

        $client = Client::query()->findOrFail($clientId);
        $this->authorize('view', $client);

        $scheduled = $scheduledId > 0
            ? ScheduledVisit::query()->with('oneOffTasks')->find($scheduledId)
            : null;

        if ($scheduled !== null) {
            $this->authorize('view', $scheduled);
        }

        return response()->json([
            'tasks' => $preview->forClientOnDate($client, $serviceDate, $scheduled),
        ]);
    }

    public function store(ScheduledVisitRequest $request, ScheduledVisitService $visits): RedirectResponse
    {
        $visit = $visits->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled visit created.')]);

        return redirect()->route('scheduled-visits.show', $visit);
    }

    public function show(Request $request, ScheduledVisit $scheduledVisit, VisitClockInService $clockIn): Response
    {
        $this->authorize('view', $scheduledVisit);

        $scheduledVisit->load([
            'client',
            'employee',
            'supervisor',
            'shiftTemplate',
            'oneOffTasks',
            'visit.tasks.skipReason',
            'visit.exceptions.visitTask',
            'visit.client',
            'visit.employee',
            'visit.scheduledVisit.shiftTemplate',
        ]);

        $user = $request->user();
        $recorded = $scheduledVisit->visit;
        $phase = $this->visitPhase($scheduledVisit, $user);
        $canUpdate = ($user?->can('update', $scheduledVisit) ?? false)
            && $scheduledVisit->status !== ScheduledVisitStatus::InProgress
            && $phase !== 'completed';

        $activeVisit = null;
        $clockInVisit = null;

        if ($user?->isDsp() && $user->employee !== null && $phase !== 'completed') {
            $active = $clockIn->activeVisitFor($user->employee);
            $activeVisit = $active === null ? null : DirectoryPresenter::activeVisitSummary($active);

            if ($phase === 'eligible' && ($active === null || $active->scheduled_visit_id === $scheduledVisit->id)) {
                $clockInVisit = DirectoryPresenter::clockInVisitSummary($scheduledVisit);
            }
        }

        return Inertia::render('scheduled-visits/show', [
            'visit' => [
                ...DirectoryPresenter::scheduledVisitDetail($scheduledVisit),
                'visit_phase' => $phase,
                'start_unavailable_reason' => $this->startUnavailableReason($scheduledVisit, $phase),
                'recorded_visit' => $recorded === null ? null : DirectoryPresenter::visitDetail($recorded),
            ],
            'can' => [
                'update' => $canUpdate,
                'clock_in' => ($user?->can('clockIn', $scheduledVisit) ?? false)
                    && $phase === 'eligible',
            ],
            'activeVisit' => $activeVisit,
            'clockInVisit' => $clockInVisit,
        ]);
    }

    public function edit(Request $request, ScheduledVisit $scheduledVisit): Response
    {
        $this->authorize('update', $scheduledVisit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $scheduledVisit->load(['client', 'employee', 'supervisor', 'shiftTemplate', 'oneOffTasks']);

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
            'catalog_services' => CareServicePresenter::options(
                CareService::query()->active()->orderBy('sort_order')->orderBy('name')->get()
            ),
            'care_preview_url' => route('scheduled-visits.care-preview'),
        ];
    }

    private function visitPhase(ScheduledVisit $visit, ?User $user): string
    {
        $recorded = $visit->visit;

        if ($visit->status === ScheduledVisitStatus::Cancelled) {
            return 'cancelled';
        }

        if ($recorded?->status === VisitStatus::Completed || $visit->status === ScheduledVisitStatus::Completed) {
            return 'completed';
        }

        if ($recorded?->status === VisitStatus::InProgress || $visit->status === ScheduledVisitStatus::InProgress) {
            return 'active';
        }

        if ($visit->isEligibleToStart() && ($user?->can('clockIn', $visit) ?? false)) {
            return 'eligible';
        }

        return 'upcoming';
    }

    private function startUnavailableReason(ScheduledVisit $visit, string $phase): ?string
    {
        if ($phase !== 'upcoming') {
            return null;
        }

        return 'Start Visit becomes available on '.$visit->service_date->toDateString().' during '.DirectoryPresenter::visitTimeLabel($visit).'. Future visits cannot be started early.';
    }
}
