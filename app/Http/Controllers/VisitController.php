<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Models\VisitException;
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

        if ($visit->status === VisitStatus::InProgress) {
            $tasks->generate($visit);
        }

        $visit->load([
            'client',
            'employee',
            'scheduledVisit.shiftTemplate',
            'tasks.skipReason',
            'exceptions.visitTask',
            'exceptions.reviewedBy',
            'exceptions.resolvedBy',
        ]);

        $user = $request->user();
        $canRecord = $user?->can('recordTask', $visit) ?? false;

        return Inertia::render('visits/show', [
            'visit' => DirectoryPresenter::visitDetail($visit),
            'skip_reasons' => $canRecord ? DirectoryPresenter::skipReasons() : [],
            'can' => [
                'clock_in' => $user?->can('clockIn', $visit->scheduledVisit) ?? false,
                'record_tasks' => $canRecord,
                'update_notes' => $user?->can('updateNotes', $visit) ?? false,
                'clock_out' => $user?->can('clockOut', $visit) ?? false,
                'view_exceptions' => $user?->can('viewAny', VisitException::class) ?? false,
            ],
        ]);
    }
}
