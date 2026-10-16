<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplianceTemporaryExceptionRequest;
use App\Http\Requests\RevokeComplianceTemporaryExceptionRequest;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Services\ComplianceExceptionService;
use Illuminate\Http\RedirectResponse;

class ComplianceTemporaryExceptionController extends Controller
{
    public function store(
        ComplianceTemporaryExceptionRequest $request,
        Employee $employee,
        ComplianceExceptionService $exceptions,
    ): RedirectResponse {
        $this->authorize('view', $employee);

        $exceptions->apply(
            $request->user(),
            $employee,
            $request->requirement(),
            $request->validated(),
        );

        return back()->with('success', 'Temporary compliance exception applied. Underlying compliance state is unchanged.');
    }

    public function revoke(
        RevokeComplianceTemporaryExceptionRequest $request,
        ComplianceTemporaryException $exception,
        ComplianceExceptionService $exceptions,
    ): RedirectResponse {
        $exceptions->revoke(
            $request->user(),
            $exception,
            (string) $request->validated('revoke_reason'),
        );

        return back()->with('success', 'Temporary compliance exception revoked.');
    }
}
