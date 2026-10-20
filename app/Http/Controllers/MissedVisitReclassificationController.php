<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReclassifyMissedVisitRequest;
use App\Models\ScheduledVisit;
use App\Services\MissedVisitReclassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class MissedVisitReclassificationController extends Controller
{
    public function store(
        ReclassifyMissedVisitRequest $request,
        ScheduledVisit $scheduledVisit,
        MissedVisitReclassificationService $reclassifications,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $reclassifications->reclassifyMissedToCancelled($scheduledVisit, $user, $request->payload());

        return Redirect::route('attendance.show', $scheduledVisit)
            ->with('success', 'Missed visit reclassified to Cancelled. Original Missed history is preserved; no worked hours were created.');
    }
}
