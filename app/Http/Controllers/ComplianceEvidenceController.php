<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplianceEvidenceReviewRequest;
use App\Http\Requests\ComplianceEvidenceSubmitRequest;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Services\ComplianceEvidenceVerificationService;
use App\Services\SchedulingNotificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ComplianceEvidenceController extends Controller
{
    public function store(
        ComplianceEvidenceSubmitRequest $request,
        Employee $employee,
        ComplianceEvidenceVerificationService $verification,
        SchedulingNotificationService $notifications,
    ): RedirectResponse {
        $data = $request->validated();
        if ($request->hasFile('document')) {
            $data['document'] = $request->file('document');
        }

        $kind = ($data['kind'] ?? null) === 'training' ? 'training' : 'credential';

        if ($kind === 'training') {
            $verification->submitTraining($request->user(), $employee, $data);
        } else {
            $verification->submitCredential($request->user(), $employee, $data);
        }

        $notifications->complianceEvidenceSubmitted($employee, $kind);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Evidence submitted for review. Pending Review does not restore eligibility.'),
        ]);

        return back();
    }

    public function reviewCredential(
        ComplianceEvidenceReviewRequest $request,
        EmployeeCredential $credential,
        ComplianceEvidenceVerificationService $verification,
    ): RedirectResponse {
        $this->authorize('view', $credential->employee);
        $data = $request->validated();

        if ($data['decision'] === 'verify') {
            $verification->verifyCredential($request->user(), $credential, $data['note'] ?? null);

            return back()->with('success', 'Credential verified. Authoritative compliance record updated.');
        }

        $verification->rejectCredential($request->user(), $credential, (string) $data['note']);

        return back()->with('success', 'Credential submission rejected. Prior verified evidence remains unchanged.');
    }

    public function reviewTraining(
        ComplianceEvidenceReviewRequest $request,
        EmployeeTraining $training,
        ComplianceEvidenceVerificationService $verification,
    ): RedirectResponse {
        $this->authorize('view', $training->employee);
        $data = $request->validated();

        if ($data['decision'] === 'verify') {
            $verification->verifyTraining($request->user(), $training, $data['note'] ?? null);

            return back()->with('success', 'Training verified. Authoritative compliance record updated.');
        }

        $verification->rejectTraining($request->user(), $training, (string) $data['note']);

        return back()->with('success', 'Training submission rejected. Prior verified evidence remains unchanged.');
    }
}
