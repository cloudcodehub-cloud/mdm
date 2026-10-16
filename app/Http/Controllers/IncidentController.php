<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscardIncidentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\Visit;
use App\Services\IncidentService;
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

        return Inertia::render('incidents/show', [
            'incident' => $incidents->serializeDetail($incident, $user),
            'can' => [
                'acknowledge' => $user->can('acknowledge', $incident),
                'manage' => $user->can('manage', $incident),
            ],
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
}
