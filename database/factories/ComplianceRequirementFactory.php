<?php

namespace Database\Factories;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceRequirementSource;
use App\Enums\CredentialType;
use App\Models\ComplianceRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ComplianceRequirement>
 */
class ComplianceRequirementFactory extends Factory
{
    protected $model = ComplianceRequirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(CredentialType::cases());

        return [
            'code' => 'req_'.Str::lower(Str::random(8)),
            'name' => $type->label(),
            'description' => null,
            'source_kind' => ComplianceRequirementSource::Credential,
            'source_key' => $type->value,
            'applicability' => ComplianceApplicability::Dsp,
            'enforcement_level' => ComplianceEnforcementLevel::Warning,
            'override_policy' => ComplianceOverridePolicy::NonOverrideable,
            'is_active' => true,
        ];
    }

    public function hardStop(): static
    {
        return $this->state(fn (): array => [
            'enforcement_level' => ComplianceEnforcementLevel::HardStop,
        ]);
    }

    public function temporaryExceptionEligible(): static
    {
        return $this->state(fn (): array => [
            'enforcement_level' => ComplianceEnforcementLevel::HardStop,
            'override_policy' => ComplianceOverridePolicy::TemporaryException,
        ]);
    }

    public function nonOverrideable(): static
    {
        return $this->state(fn (): array => [
            'enforcement_level' => ComplianceEnforcementLevel::HardStop,
            'override_policy' => ComplianceOverridePolicy::NonOverrideable,
        ]);
    }

    public function forCredentialType(CredentialType $type): static
    {
        return $this->state(fn (): array => [
            'code' => 'cred_'.$type->value,
            'name' => $type->label(),
            'source_kind' => ComplianceRequirementSource::Credential,
            'source_key' => $type->value,
        ]);
    }

    public function forTraining(string $title, ?string $code = null): static
    {
        return $this->state(fn (): array => [
            'code' => $code ?? 'train_'.Str::slug($title, '_'),
            'name' => $title,
            'source_kind' => ComplianceRequirementSource::Training,
            'source_key' => Str::lower(trim($title)),
        ]);
    }
}
