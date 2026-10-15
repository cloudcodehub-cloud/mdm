<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcknowledgeSafetyCheckRequest;
use App\Models\Visit;
use App\Models\VisitSafetyCheck;
use App\Services\VisitSafetyCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VisitSafetyCheckController extends Controller
{
    public function __construct(private VisitSafetyCheckService $safetyChecks) {}

    public function confirmGeneral(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('recordSafety', $visit);

        $this->safetyChecks->confirmGeneral($visit);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Visit-level safety confirmation recorded.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }

    public function acknowledge(
        AcknowledgeSafetyCheckRequest $request,
        Visit $visit,
        VisitSafetyCheck $safetyCheck,
    ): RedirectResponse {
        abort_unless($safetyCheck->visit_id === $visit->id, 404);

        $this->safetyChecks->acknowledge($visit, $safetyCheck, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Safety check recorded.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }
}
