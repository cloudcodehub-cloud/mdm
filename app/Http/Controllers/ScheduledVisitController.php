<?php

namespace App\Http\Controllers;

use App\Enums\ScheduledVisitStatus;
use App\Enums\SeriesEditScope;
use App\Enums\VisitAssignmentKind;
use App\Enums\VisitRecurrencePattern;
use App\Enums\VisitStatus;
use App\Http\Requests\DuplicateScheduledVisitRequest;
use App\Http\Requests\ReplaceScheduledVisitRequest;
use App\Http\Requests\ScheduledVisitRequest;
use App\Models\CareService;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ScheduleCalendarService;
use App\Services\ScheduledVisitService;
use App\Services\SchedulingMatchService;
use App\Services\SchedulingNotificationService;
use App\Services\VisitAssignmentService;
use App\Services\VisitCarePreviewService;
use App\Services\VisitClockInService;
use App\Services\VisitSeriesService;
use App\Support\CareServicePresenter;
use App\Support\DirectoryPresenter;
use App\Support\VisitHistoryQuery;
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

        $filters = VisitHistoryQuery::filters($request);

        $query = ScheduledVisit::query()
            ->with([
                'client',
                'employee',
                'supervisor',
                'shiftTemplate',
                'careServices',
                'visit.exceptions',
            ])
            ->visibleTo($user);

        VisitHistoryQuery::apply($query, $filters);

        if (VisitHistoryQuery::prefersNewestFirst($filters)) {
            $query->orderByDesc('service_date')->orderByDesc('id');
        } else {
            $query->orderBy('service_date')->orderBy('id');
        }

        $visits = $query->paginate(12)->withQueryString();
        $filterQuery = VisitHistoryQuery::queryString($filters);

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
            'supervisors' => $user->isDsp() ? [] : DirectoryPresenter::supervisorOptions(),
            'service_types' => ScheduledVisit::query()
                ->visibleTo($user)
                ->select('service_type')
                ->distinct()
                ->orderBy('service_type')
                ->pluck('service_type')
                ->values()
                ->all(),
            'can' => [
                'create' => $user->can('create', ScheduledVisit::class),
                'filter_dsps' => ! $user->isDsp(),
                'filter_supervisors' => $user->isAdmin(),
            ],
            'board_url' => route('scheduled-visits.calendar', $filterQuery),
        ]);
    }

    public function calendar(Request $request, ScheduleCalendarService $calendar): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', ScheduledVisit::class);

        $filters = [
            'client_id' => $request->string('client_id')->value(),
            'employee_id' => $request->string('employee_id')->value(),
            'supervisor_id' => $request->string('supervisor_id')->value(),
            'service_type' => $request->string('service_type')->value(),
            'status' => $request->string('status')->value(),
            'phase' => $request->string('phase')->value(),
            'from' => $request->string('from')->value(),
            'to' => $request->string('to')->value(),
        ];

        if ($filters['status'] === '' && $filters['phase'] !== '') {
            $filters['status'] = match ($filters['phase']) {
                'upcoming' => ScheduledVisitStatus::Scheduled->value,
                'in_progress' => ScheduledVisitStatus::InProgress->value,
                'completed' => ScheduledVisitStatus::Completed->value,
                'cancelled' => ScheduledVisitStatus::Cancelled->value,
                default => '',
            };
        }

        $listFilters = VisitHistoryQuery::queryString($filters);

        return Inertia::render('scheduled-visits/calendar', [
            'board' => $calendar->view(
                $user,
                $request->string('view')->value() ?: 'week',
                $request->string('group')->value() ?: 'dsp',
                $request->string('date')->value(),
                $filters,
            ),
            'filters' => $filters,
            'clients' => DirectoryPresenter::clientFilterOptions($user),
            'dsps' => DirectoryPresenter::dspFilterOptions($user),
            'supervisors' => $user->isDsp() ? [] : DirectoryPresenter::supervisorOptions(),
            'can' => [
                'create' => $user->can('create', ScheduledVisit::class),
                'filter_dsps' => ! $user->isDsp(),
                'filter_supervisors' => $user->isAdmin(),
            ],
            'list_url' => route('scheduled-visits.index', $listFilters),
        ]);
    }

    public function availabilityBoard(Request $request, SchedulingMatchService $matching): JsonResponse
    {
        $this->authorize('create', ScheduledVisit::class);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $existing = null;
        $existingId = (int) $request->integer('scheduled_visit_id');

        if ($existingId > 0) {
            $existing = ScheduledVisit::query()->find($existingId);
        }

        return response()->json($matching->board($user, [
            'client_id' => $request->integer('client_id'),
            'service_date' => $request->string('service_date')->value(),
            'starts_at' => $request->string('starts_at')->value(),
            'ends_at' => $request->string('ends_at')->value(),
            'shift_template_id' => $request->input('shift_template_id'),
            'service_type' => $request->string('service_type')->value(),
        ], $existing));
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

        $serviceIds = [];

        foreach ((array) $request->input('service_ids', []) as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $serviceIds[] = (int) $value;
            }
        }

        $scheduled = $scheduledId > 0
            ? ScheduledVisit::query()->with(['oneOffTasks', 'taskOverrides'])->find($scheduledId)
            : null;

        if ($scheduled !== null) {
            $this->authorize('view', $scheduled);
        }

        return response()->json($preview->forClientOnDate($client, $serviceDate, $serviceIds, $scheduled));
    }

    public function store(ScheduledVisitRequest $request, ScheduledVisitService $visits, VisitSeriesService $series): RedirectResponse
    {
        $data = $request->validated();

        if ($request->boolean('repeat')) {
            $created = $series->createSeries($data, [
                'pattern' => $data['repeat_pattern'] ?? VisitRecurrencePattern::Weekly->value,
                'interval' => $data['repeat_interval'] ?? 1,
                'ends_on' => $data['repeat_ends_on'] ?? null,
                'occurrence_count' => $data['repeat_count'] ?? null,
                'days_of_week' => $data['repeat_days'] ?? null,
            ]);
            $visit = $created[0] ?? $visits->create($data);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring visits scheduled.')]);

            return redirect()->route('scheduled-visits.show', $visit);
        }

        $visit = $visits->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled visit created.')]);

        return redirect()->route('scheduled-visits.show', $visit);
    }

    public function show(Request $request, ScheduledVisit $scheduledVisit, VisitClockInService $clockIn, VisitCarePreviewService $carePreview): Response
    {
        $this->authorize('view', $scheduledVisit);

        $scheduledVisit->load([
            'client',
            'employee',
            'supervisor',
            'shiftTemplate',
            'oneOffTasks',
            'careServices',
            'taskOverrides',
            'series',
            'assignments.employee',
            'assignments.assignedBy',
            'createdBy',
            'updatedBy',
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
                'assigned_visit_tasks' => $carePreview->assignedTasksFor($scheduledVisit),
                'recorded_visit' => $recorded === null ? null : DirectoryPresenter::visitDetail($recorded),
            ],
            'can' => [
                'update' => $canUpdate,
                'replace' => $canUpdate,
                'duplicate' => $user?->can('create', ScheduledVisit::class) ?? false,
                'clock_in' => ($user?->can('clockIn', $scheduledVisit) ?? false)
                    && $phase === 'eligible',
            ],
            'documents' => [
                'visit_handout' => route('documents.visit-handout.preview', $scheduledVisit),
            ],
            'activeVisit' => $activeVisit,
            'clockInVisit' => $clockInVisit,
            'dsps' => ($user !== null && ($user->can('update', $scheduledVisit) || $user->can('create', ScheduledVisit::class)))
                ? DirectoryPresenter::schedulingDspOptions($user, $scheduledVisit->employee)
                : [],
        ]);
    }

    public function edit(Request $request, ScheduledVisit $scheduledVisit): Response
    {
        $this->authorize('update', $scheduledVisit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $scheduledVisit->load(['client', 'employee', 'supervisor', 'shiftTemplate', 'oneOffTasks', 'careServices', 'taskOverrides']);

        return Inertia::render('scheduled-visits/edit', [
            'visit' => DirectoryPresenter::scheduledVisitDetail($scheduledVisit),
            ...$this->formOptions($user, $scheduledVisit),
        ]);
    }

    public function update(
        ScheduledVisitRequest $request,
        ScheduledVisit $scheduledVisit,
        ScheduledVisitService $visits,
        VisitSeriesService $series,
    ): RedirectResponse {
        $data = $request->validated();
        $scope = SeriesEditScope::tryFrom((string) $request->input('series_scope', 'this')) ?? SeriesEditScope::This;

        if ($data['status'] === ScheduledVisitStatus::Cancelled->value && $scheduledVisit->series_id !== null) {
            $series->cancelWithScope(
                $scheduledVisit,
                $scope,
                $data['cancellation_reason'] ?? null,
                $request->user()?->id,
            );
        } elseif ($scheduledVisit->series_id !== null && $scope !== SeriesEditScope::This) {
            $series->updateWithScope($scheduledVisit, $data, $scope);
        } else {
            $visits->update($scheduledVisit, $data);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled visit updated.')]);

        return redirect()->route('scheduled-visits.show', $scheduledVisit);
    }

    public function duplicate(
        DuplicateScheduledVisitRequest $request,
        ScheduledVisit $scheduledVisit,
        ScheduledVisitService $visits,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $date = match ($request->string('preset')->value()) {
            'tomorrow' => $scheduledVisit->service_date->addDay()->toDateString(),
            'next_week' => $scheduledVisit->service_date->addWeek()->toDateString(),
            default => (string) $request->string('service_date')->value(),
        };

        $copy = $visits->duplicate($scheduledVisit, $date, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit duplicated.')]);

        return redirect()->route('scheduled-visits.show', $copy);
    }

    public function replace(
        ReplaceScheduledVisitRequest $request,
        ScheduledVisit $scheduledVisit,
        VisitAssignmentService $assignments,
        SchedulingNotificationService $notifications,
        ScheduledVisitService $visits,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $replacement = Employee::query()->findOrFail((int) $request->integer('employee_id'));
        $previous = $scheduledVisit->employee;
        $payload = [
            'client_id' => $scheduledVisit->client_id,
            'employee_id' => $replacement->id,
            'supervisor_id' => $scheduledVisit->supervisor_id,
            'shift_template_id' => $scheduledVisit->shift_template_id,
            'service_date' => $scheduledVisit->service_date->toDateString(),
            'starts_at' => $scheduledVisit->starts_at,
            'ends_at' => $scheduledVisit->ends_at,
            'service_type' => $scheduledVisit->service_type,
            'status' => $scheduledVisit->status->value,
        ];

        $visits->assertSchedulable($user, $payload, $scheduledVisit);

        $visit = $assignments->reassign(
            $scheduledVisit->loadMissing('shiftTemplate'),
            $replacement,
            $user,
            $request->string('reason')->value(),
            VisitAssignmentKind::Replacement,
            $request->boolean('mark_call_off'),
        );

        $notifications->visitReassigned($visit, $previous);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('DSP replacement saved.')]);

        return redirect()->route('scheduled-visits.show', $visit);
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
            'availability_board_url' => route('scheduled-visits.availability-board'),
            'is_admin' => $user->isAdmin(),
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
