<?php

namespace App\Http\Controllers;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceRequirementSource;
use App\Enums\CredentialType;
use App\Http\Requests\ComplianceRequirementRequest;
use App\Models\ComplianceRequirement;
use App\Services\ComplianceRequirementAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceRequirementController extends Controller
{
    public function index(Request $request, ComplianceRequirementAdminService $admin): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('manage', ComplianceRequirement::class);

        return Inertia::render('compliance/requirements', [
            'requirements' => $admin->catalog(),
            'options' => [
                'source_kinds' => collect(ComplianceRequirementSource::cases())
                    ->map(fn (ComplianceRequirementSource $case): array => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values()
                    ->all(),
                'credential_types' => collect(CredentialType::cases())
                    ->map(fn (CredentialType $case): array => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values()
                    ->all(),
                'applicabilities' => collect(ComplianceApplicability::cases())
                    ->map(fn (ComplianceApplicability $case): array => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values()
                    ->all(),
                'enforcement_levels' => collect(ComplianceEnforcementLevel::cases())
                    ->map(fn (ComplianceEnforcementLevel $case): array => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values()
                    ->all(),
                'override_policies' => collect(ComplianceOverridePolicy::cases())
                    ->map(fn (ComplianceOverridePolicy $case): array => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(
        ComplianceRequirementRequest $request,
        ComplianceRequirementAdminService $admin,
    ): RedirectResponse {
        $admin->create($request->user(), $request->validated());

        return back()->with('success', 'Compliance requirement created. New Hard Stops apply only when this requirement is active.');
    }

    public function update(
        ComplianceRequirementRequest $request,
        ComplianceRequirement $requirement,
        ComplianceRequirementAdminService $admin,
    ): RedirectResponse {
        $admin->update($request->user(), $requirement, $request->validated());

        return back()->with('success', 'Compliance requirement updated. Existing evidence records were preserved.');
    }

    public function activate(
        Request $request,
        ComplianceRequirement $requirement,
        ComplianceRequirementAdminService $admin,
    ): RedirectResponse {
        $this->authorize('manage', ComplianceRequirement::class);
        $admin->setActive($request->user(), $requirement, true);

        return back()->with('success', 'Compliance requirement activated.');
    }

    public function deactivate(
        Request $request,
        ComplianceRequirement $requirement,
        ComplianceRequirementAdminService $admin,
    ): RedirectResponse {
        $this->authorize('manage', ComplianceRequirement::class);
        $admin->setActive($request->user(), $requirement, false);

        return back()->with('success', 'Compliance requirement deactivated. Historical evidence and exceptions remain.');
    }
}
