<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Http\Requests\CloseIncidentRequest;
use App\Http\Requests\CompleteIncidentFollowUpRequest;
use App\Http\Requests\DiscardIncidentRequest;
use App\Http\Requests\ReassignIncidentFollowUpRequest;
use App\Http\Requests\ReopenIncidentRequest;
use App\Http\Requests\StoreIncidentAmendmentRequest;
use App\Http\Requests\StoreIncidentFollowUpRequest;
use App\Http\Requests\StoreIncidentReportingRecordRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\StoreIncidentReviewRequest;
use App\Http\Requests\UpdateIncidentClassificationRequest;
use App\Http\Requests\UpdateIncidentInvestigationRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentFollowUp;
use App\Models\User;
use App\Models\Visit;
use App\Services\IncidentService;
use App\Services\IncidentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(Request $request, IncidentService $incidents): Response
    {
        $this->authorize('viewAny', Incident::class);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $incidents->escalateOverdueAcknowledgments();

        $page = $incidents->registerQueryFor($user)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('incidents/index', [
            'incidents' => [
                'data' => $page->getCollection()
                    ->map(fn (Incident $incident): array => $incidents->serializeListItem($incident, $user))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'total' => $page->total(),
                ],
                'links' => [
                    'prev' => $page->previousPageUrl(),
                    'next' => $page->nextPageUrl(),
                ],
            ],
            'acknowledgment_threshold_minutes' => $incidents->acknowledgmentThresholdMinutes(),
            'can' => [
                'acknowledge' => $user->isAdmin() || $user->isSupervisor(),
            ],
        ]);
    }

    public function show(Request $request, Incident $incident, IncidentService $incidents): Response
    {
        $this->authorize('view', $incident);

        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($incidents->isOverdueForAcknowledgment($incident)) {
            $incidents->escalateOverdueAcknowledgments();
            $incident->refresh();
        }

        $canManage = $user->can('manage', $incident);

        return Inertia::render('incidents/show', [
            'incident' => $incidents->serializeDetail($incident, $user),
            'can' => [
                'acknowledge' => $user->can('acknowledge', $incident),
                'manage' => $canManage,
                'amend' => $user->can('amend', $incident),
                'close' => $user->can('close', $incident),
                'reopen' => $user->can('reopen', $incident),
            ],
            'categories' => $canManage ? $incidents->categoryOptions() : [],
            'assignee_options' => $canManage
                ? Employee::query()
                    ->operational()
                    ->where('employment_status', EmploymentStatus::Active)
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->map(fn (Employee $employee): array => [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    public function store(
        StoreIncidentRequest $request,
        Visit $visit,
        IncidentService $incidents,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $payload = $request->validated();
        $submit = (bool) ($payload['submit'] ?? false);
        unset($payload['submit']);

        $incident = $submit
            ? $incidents->submit($user, $visit, $payload)
            : $incidents->saveDraft($user, $visit, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $submit
                ? __('Incident submitted as :number.', ['number' => $incident->tracking_number])
                : __('Incident draft saved.'),
        ]);

        return $submit
            ? redirect()->route('incidents.show', $incident)
            : redirect()->route('visits.show', $visit);
    }

    public function update(
        UpdateIncidentRequest $request,
        Incident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $visit = $incident->visit;
        $payload = $request->validated();
        $submit = (bool) ($payload['submit'] ?? false);
        unset($payload['submit']);

        $incident = $submit
            ? $incidents->submit($user, $visit, $payload, $incident)
            : $incidents->saveDraft($user, $visit, $payload, $incident);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $submit
                ? __('Incident submitted as :number.', ['number' => $incident->tracking_number])
                : __('Incident draft saved.'),
        ]);

        return $submit
            ? redirect()->route('incidents.show', $incident)
            : redirect()->route('visits.show', $visit);
    }

    public function discard(
        DiscardIncidentRequest $request,
        Incident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $visitId = $incident->visit_id;
        $incidents->discardDraft($user, $incident, $request->validated('reason'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Incident draft discarded.'),
        ]);

        return redirect()->route('visits.show', $visitId);
    }

    public function acknowledge(
        Request $request,
        Incident $incident,
        IncidentService $incidents,
    ): RedirectResponse {
        $this->authorize('acknowledge', $incident);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $incidents->acknowledge($user, $incident);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Incident acknowledged. Management review may continue.'),
        ]);

        return redirect()->route('incidents.show', $incident);
    }

    public function updateClassification(
        UpdateIncidentClassificationRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->updateOfficialClassification($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Official classification updated.'));
    }

    public function storeReview(
        StoreIncidentReviewRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->addReview($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Management review recorded.'));
    }

    public function updateInvestigation(
        UpdateIncidentInvestigationRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->updateInvestigation($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Investigation details saved.'));
    }

    public function storeFollowUp(
        StoreIncidentFollowUpRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->createFollowUp($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Follow-up assigned.'));
    }

    public function completeFollowUp(
        CompleteIncidentFollowUpRequest $request,
        IncidentFollowUp $incidentFollowUp,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->completeFollowUp($this->actor($request), $incidentFollowUp, $request->validated());

        return $this->flashAndShow($incidentFollowUp->incident, __('Follow-up completed.'));
    }

    public function reassignFollowUp(
        ReassignIncidentFollowUpRequest $request,
        IncidentFollowUp $incidentFollowUp,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->reassignFollowUp($this->actor($request), $incidentFollowUp, $request->validated());

        return $this->flashAndShow($incidentFollowUp->incident, __('Follow-up reassigned.'));
    }

    public function storeReportingRecord(
        StoreIncidentReportingRecordRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->addReportingRecord($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Reporting record added.'));
    }

    public function storeAmendment(
        StoreIncidentAmendmentRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->addAmendment($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Amendment recorded. The original content is preserved in history.'));
    }

    public function close(
        CloseIncidentRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->close($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Incident closed.'));
    }

    public function reopen(
        ReopenIncidentRequest $request,
        Incident $incident,
        IncidentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->reopen($this->actor($request), $incident, $request->validated());

        return $this->flashAndShow($incident, __('Incident reopened and returned to review.'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $user;
    }

    private function flashAndShow(Incident $incident, string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return redirect()->route('incidents.show', $incident);
    }
}
