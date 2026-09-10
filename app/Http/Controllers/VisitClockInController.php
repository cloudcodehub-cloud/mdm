<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClockInRequest;
use App\Models\ScheduledVisit;
use App\Services\VisitClockInService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VisitClockInController extends Controller
{
    public function store(
        ClockInRequest $request,
        ScheduledVisit $scheduledVisit,
        VisitClockInService $clockIn,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $visit = $clockIn->clockIn($user, $scheduledVisit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit started.')]);

        return redirect()->route('visits.show', $visit);
    }
}
