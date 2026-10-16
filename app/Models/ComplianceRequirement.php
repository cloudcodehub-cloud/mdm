<?php

namespace App\Models;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceRequirementSource;
use Database\Factories\ComplianceRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property ComplianceRequirementSource $source_kind
 * @property string $source_key
 * @property ComplianceApplicability $applicability
 * @property ComplianceEnforcementLevel $enforcement_level
 * @property ComplianceOverridePolicy $override_policy
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'code',
    'name',
    'description',
    'source_kind',
    'source_key',
    'applicability',
    'enforcement_level',
    'override_policy',
    'is_active',
])]
class ComplianceRequirement extends Model
{
    /** @use HasFactory<ComplianceRequirementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_kind' => ComplianceRequirementSource::class,
            'applicability' => ComplianceApplicability::class,
            'enforcement_level' => ComplianceEnforcementLevel::class,
            'override_policy' => ComplianceOverridePolicy::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ComplianceTemporaryException, $this>
     */
    public function temporaryExceptions(): HasMany
    {
        return $this->hasMany(ComplianceTemporaryException::class);
    }

    /**
     * @return HasMany<ComplianceRequirementAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ComplianceRequirementAudit::class)->orderByDesc('id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isHardStop(): bool
    {
        return $this->enforcement_level->blocksService();
    }

    public function allowsTemporaryException(): bool
    {
        return $this->isHardStop() && $this->override_policy->allowsTemporaryException();
    }
}
