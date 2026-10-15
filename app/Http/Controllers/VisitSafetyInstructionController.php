<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitSafetyInstructionRequest;
use App\Models\Visit;
use App\Models\VisitSafetyInstruction;
use App\Services\VisitSafetyInstructionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VisitSafetyInstructionController extends Controller
{
    public function __construct(private VisitSafetyInstructionService $instructions) {}

    public function store(VisitSafetyInstructionRequest $request, Visit $visit): RedirectResponse
    {
        $this->authorize('issueSafetyInstruction', $visit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->instructions->issue($visit, $user, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Urgent visit safety instruction issued.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }

    public function acknowledge(
        Request $request,
        Visit $visit,
        VisitSafetyInstruction $safetyInstruction,
    ): RedirectResponse {
        abort_unless($safetyInstruction->visit_id === $visit->id, 404);
        $this->authorize('acknowledgeSafetyInstruction', $visit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->instructions->acknowledge($visit, $safetyInstruction, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Visit safety instruction acknowledged.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }
}
