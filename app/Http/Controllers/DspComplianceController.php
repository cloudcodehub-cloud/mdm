<?php

namespace App\Http\Controllers;

use App\Enums\CredentialType;
use App\Services\ComplianceEligibilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DspComplianceController extends Controller
{
    public function __invoke(Request $request, ComplianceEligibilityService $eligibility): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($user->isDsp(), 403);

        $employee = $user->employee;
        abort_unless($employee !== null, 403);

        return Inertia::render('compliance/mine', [
            ...$eligibility->selfView($employee),
            'can_submit_evidence' => true,
            'credential_type_options' => collect(CredentialType::cases())
                ->map(fn (CredentialType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
