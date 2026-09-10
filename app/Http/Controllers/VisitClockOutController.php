<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClockOutRequest;
use App\Http\Requests\UpdateVisitNotesRequest;
use App\Models\Visit;
use App\Services\VisitClockOutService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VisitClockOutController extends Controller
{
    public function updateNotes(
        UpdateVisitNotesRequest $request,
        Visit $visit,
        VisitClockOutService $clockOut,
    ): RedirectResponse {
        $clockOut->updateNotes($visit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit notes saved.')]);

        return back();
    }

    public function store(
        ClockOutRequest $request,
        Visit $visit,
        VisitClockOutService $clockOut,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $clockOut->clockOut($user, $visit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit completed.')]);

        return redirect()->route('visits.show', $visit);
    }
}
