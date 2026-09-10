<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Services\VisitTaskGenerator;
use App\Support\DirectoryPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function show(Request $request, Visit $visit, VisitTaskGenerator $tasks): Response
    {
        $this->authorize('view', $visit);

        $tasks->generate($visit);

        $visit->load([
            'client',
            'employee',
            'scheduledVisit.shiftTemplate',
            'tasks',
        ]);

        $user = $request->user();

        return Inertia::render('visits/show', [
            'visit' => DirectoryPresenter::visitDetail($visit),
            'can' => [
                'clock_in' => $user?->can('clockIn', $visit->scheduledVisit) ?? false,
            ],
        ]);
    }
}
