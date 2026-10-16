<?php

namespace App\Services;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceRequirementSource;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComplianceRequirementAdminService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return ComplianceRequirement::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (ComplianceRequirement $requirement): array => $this->serialize($requirement))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ComplianceRequirement
    {
        $this->assertAdmin($actor);
        $payload = $this->normalized($data);

        return DB::transaction(function () use ($actor, $payload): ComplianceRequirement {
            $requirement = ComplianceRequirement::query()->create($payload);
            $this->audit($requirement, $actor, 'created', null, $this->snapshot($requirement));

            return $requirement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ComplianceRequirement $requirement, array $data): ComplianceRequirement
    {
        $this->assertAdmin($actor);
        $before = $this->snapshot($requirement);
        $payload = $this->normalized($data, $requirement);

        return DB::transaction(function () use ($actor, $requirement, $payload, $before): ComplianceRequirement {
            $requirement->update($payload);
            $requirement->refresh();
            $this->audit($requirement, $actor, 'updated', $before, $this->snapshot($requirement));

            return $requirement;
        });
    }

    public function setActive(User $actor, ComplianceRequirement $requirement, bool $active): ComplianceRequirement
    {
        $this->assertAdmin($actor);
        $before = $this->snapshot($requirement);

        return DB::transaction(function () use ($actor, $requirement, $active, $before): ComplianceRequirement {
            $requirement->update(['is_active' => $active]);
            $requirement->refresh();
            $this->audit(
                $requirement,
                $actor,
                $active ? 'activated' : 'deactivated',
                $before,
                $this->snapshot($requirement),
            );

            return $requirement;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ComplianceRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'code' => $requirement->code,
            'name' => $requirement->name,
            'description' => $requirement->description,
            'source_kind' => $requirement->source_kind->value,
            'source_kind_label' => $requirement->source_kind->label(),
            'source_key' => $requirement->source_key,
            'applicability' => $requirement->applicability->value,
            'applicability_label' => $requirement->applicability->label(),
            'enforcement_level' => $requirement->enforcement_level->value,
            'enforcement_label' => $requirement->enforcement_level->label(),
            'override_policy' => $requirement->override_policy->value,
            'override_policy_label' => $requirement->override_policy->label(),
            'is_active' => $requirement->is_active,
            'is_hard_stop' => $requirement->isHardStop(),
            'updated_at' => $requirement->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, ?ComplianceRequirement $existing = null): array
    {
        $sourceKind = ComplianceRequirementSource::from((string) $data['source_kind']);
        $enforcement = ComplianceEnforcementLevel::from((string) $data['enforcement_level']);
        $override = ComplianceOverridePolicy::from((string) ($data['override_policy'] ?? ComplianceOverridePolicy::NonOverrideable->value));

        if (! $enforcement->blocksService() && $override === ComplianceOverridePolicy::TemporaryException) {
            $override = ComplianceOverridePolicy::NonOverrideable;
        }

        $code = Str::lower(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $existing?->code ?? Str::slug((string) $data['name'], '_');
        }

        $sourceKey = trim((string) $data['source_key']);
        if ($sourceKind === ComplianceRequirementSource::Training) {
            $sourceKey = Str::lower($sourceKey);
        }

        $duplicate = ComplianceRequirement::query()
            ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
            ->where('code', $code)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => 'A compliance requirement with this code already exists.',
            ]);
        }

        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'source_kind' => $sourceKind,
            'source_key' => $sourceKey,
            'applicability' => ComplianceApplicability::from((string) $data['applicability']),
            'enforcement_level' => $enforcement,
            'override_policy' => $override,
            'is_active' => filter_var($data['is_active'] ?? ($existing?->is_active ?? false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ComplianceRequirement $requirement): array
    {
        return [
            'code' => $requirement->code,
            'name' => $requirement->name,
            'description' => $requirement->description,
            'source_kind' => $requirement->source_kind->value,
            'source_key' => $requirement->source_key,
            'applicability' => $requirement->applicability->value,
            'enforcement_level' => $requirement->enforcement_level->value,
            'override_policy' => $requirement->override_policy->value,
            'is_active' => $requirement->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(
        ComplianceRequirement $requirement,
        User $actor,
        string $action,
        ?array $before,
        ?array $after,
    ): ComplianceRequirementAudit {
        return ComplianceRequirementAudit::query()->create([
            'compliance_requirement_id' => $requirement->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }

    private function assertAdmin(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'requirement' => 'Only Admin may manage Compliance Requirements.',
            ]);
        }
    }
}
