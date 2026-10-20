<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizePayrollPeriodRequest;
use App\Http\Requests\ReFinalizePayrollPeriodRequest;
use App\Http\Requests\ReopenPayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Services\PayrollFinalizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class PayrollPeriodController extends Controller
{
    public function finalize(
        FinalizePayrollPeriodRequest $request,
        PayrollFinalizationService $payroll,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $period = $payroll->finalize($user, $request->payload());

        return Redirect::back()->with(
            'success',
            'Payroll period finalized ('.$period->period_start->toDateString().'–'.$period->period_end->toDateString().').',
        );
    }

    public function reopen(
        ReopenPayrollPeriodRequest $request,
        PayrollPeriod $payrollPeriod,
        PayrollFinalizationService $payroll,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $payroll->reopen($user, $payrollPeriod, $request->payload());

        return Redirect::back()->with('success', 'Payroll record reopened for correction. Re-finalization is required before export readiness.');
    }

    public function reFinalize(
        ReFinalizePayrollPeriodRequest $request,
        PayrollPeriod $payrollPeriod,
        PayrollFinalizationService $payroll,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $payroll->reFinalize($user, $payrollPeriod, $request->payload());

        return Redirect::back()->with('success', 'Payroll record re-finalized and export-ready again.');
    }
}
