<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdministrativeCloseVisitRequest;
use App\Models\Visit;
use App\Services\VisitAdministrativeCloseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VisitAdministrativeCloseController extends Controller
{
    public function store(
        AdministrativeCloseVisitRequest $request,
        Visit $visit,
        VisitAdministrativeCloseService $closer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $closer->close($user, $visit, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Visit administratively closed. Original DSP EVV evidence was preserved.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }
}
