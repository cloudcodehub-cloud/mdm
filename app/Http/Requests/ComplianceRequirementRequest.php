<?php

namespace App\Http\Requests;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceRequirementSource;
use App\Enums\CredentialType;
use App\Models\ComplianceRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplianceRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', ComplianceRequirement::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sourceKind = $this->input('source_kind');

        return [
            'code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_kind' => ['required', Rule::enum(ComplianceRequirementSource::class)],
            'source_key' => [
                'required',
                'string',
                'max:160',
                Rule::when(
                    $sourceKind === ComplianceRequirementSource::Credential->value,
                    [Rule::enum(CredentialType::class)],
                ),
            ],
            'applicability' => ['required', Rule::enum(ComplianceApplicability::class)],
            'enforcement_level' => ['required', Rule::enum(ComplianceEnforcementLevel::class)],
            'override_policy' => ['required', Rule::enum(ComplianceOverridePolicy::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
