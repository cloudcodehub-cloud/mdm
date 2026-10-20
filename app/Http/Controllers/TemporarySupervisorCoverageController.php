<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevokeTemporarySupervisorCoverageRequest;
use App\Http\Requests\StoreTemporarySupervisorCoverageRequest;
use App\Http\Requests\UpdateTemporarySupervisorCoverageRequest;
use App\Models\Employee;
use App\Models\TemporarySupervisorCoverage;
use App\Services\TemporarySupervisorCoverageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class TemporarySupervisorCoverageController extends Controller
{
    public function store(
        StoreTemporarySupervisorCoverageRequest $request,
        Employee $employee,
        TemporarySupervisorCoverageService $coverages,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $coverages->grant($actor, $employee, $request->payload());

        return Redirect::route('supervisors.show', $employee)
            ->with('success', 'Temporary Supervisor coverage granted.');
    }

    public function update(
        UpdateTemporarySupervisorCoverageRequest $request,
        TemporarySupervisorCoverage $temporarySupervisorCoverage,
        TemporarySupervisorCoverageService $coverages,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $coverages->modify($actor, $temporarySupervisorCoverage, $request->payload());

        return Redirect::route('supervisors.show', $temporarySupervisorCoverage->original_supervisor_id)
            ->with('success', 'Temporary Supervisor coverage updated. Prior coverage history remains.');
    }

    public function revoke(
        RevokeTemporarySupervisorCoverageRequest $request,
        TemporarySupervisorCoverage $temporarySupervisorCoverage,
        TemporarySupervisorCoverageService $coverages,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $coverages->revoke($actor, $temporarySupervisorCoverage, $request->payload());

        return Redirect::route('supervisors.show', $temporarySupervisorCoverage->original_supervisor_id)
            ->with('success', 'Temporary Supervisor coverage revoked. Completed covering actions remain attributed.');
    }
}
