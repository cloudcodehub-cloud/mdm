<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteVisitTaskRequest;
use App\Http\Requests\SkipVisitTaskRequest;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\VisitTaskService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VisitTaskController extends Controller
{
    public function complete(
        CompleteVisitTaskRequest $request,
        Visit $visit,
        VisitTask $visitTask,
        VisitTaskService $tasks,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $tasks->complete($user, $visit, $visitTask, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task completed.')]);

        return back();
    }

    public function skip(
        SkipVisitTaskRequest $request,
        Visit $visit,
        VisitTask $visitTask,
        VisitTaskService $tasks,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $tasks->skip($user, $visit, $visitTask, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task skipped.')]);

        return back();
    }
}
