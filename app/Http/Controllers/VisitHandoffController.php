<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandOffVisitRequest;
use App\Models\Visit;
use App\Services\VisitHandoffService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VisitHandoffController extends Controller
{
    public function store(
        HandOffVisitRequest $request,
        Visit $visit,
        VisitHandoffService $handoffs,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $closed = $handoffs->handOff($user, $visit, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Mid-service handoff recorded. Original DSP EVV was preserved; the replacement DSP can clock in for the linked Visit.'),
        ]);

        return redirect()->route('scheduled-visits.show', $closed->scheduled_visit_id);
    }
}
