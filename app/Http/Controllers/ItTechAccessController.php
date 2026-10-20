<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateItTechAccessRequest;
use App\Models\Employee;
use App\Services\ItTechAccessService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ItTechAccessController extends Controller
{
    public function update(
        UpdateItTechAccessRequest $request,
        Employee $employee,
        ItTechAccessService $access,
    ): RedirectResponse {
        $employee->loadMissing('user');
        abort_unless($employee->user !== null, 404);

        $enabled = (bool) $request->validated('enabled');

        if ($enabled) {
            $access->grant($request->user(), $employee->user);
            $message = 'IT/Tech access granted.';
        } else {
            $access->revoke($request->user(), $employee->user);
            $message = 'IT/Tech access removed. Active tickets returned to Unassigned.';
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($message),
        ]);

        return back();
    }
}
