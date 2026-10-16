<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplianceEvidenceReviewRequest;
use App\Http\Requests\ComplianceEvidenceSubmitRequest;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Services\ComplianceEvidenceVerificationService;
use Illuminate\Http\RedirectResponse;

class ComplianceEvidenceController extends Controller
{
    public function store(
        ComplianceEvidenceSubmitRequest $request,
        Employee $employee,
        ComplianceEvidenceVerificationService $verification,
    ): RedirectResponse {
        $data = $request->validated();

        if (($data['kind'] ?? null) === 'training') {
            $verification->submitTraining($request->user(), $employee, $data);
        } else {
            $verification->submitCredential($request->user(), $employee, $data);
        }

        return back()->with('success', 'Evidence submitted for review. Pending Review does not restore eligibility.');
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
