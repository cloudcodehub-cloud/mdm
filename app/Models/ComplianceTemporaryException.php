<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ComplianceTemporaryExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $compliance_requirement_id
 * @property string $reason
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $created_by_user_id
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $revoke_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read ComplianceRequirement $requirement
 * @property-read User $createdBy
 * @property-read User|null $revokedBy
 *
 * @method static Builder<static> activeAt(CarbonInterface|string|null $at = null)
 */
#[Fillable([
    'employee_id',
    'compliance_requirement_id',
    'reason',
    'starts_at',
    'ends_at',
    'created_by_user_id',
    'revoked_at',
    'revoked_by_user_id',
    'revoke_reason',
])]
class ComplianceTemporaryException extends Model
{
    /** @use HasFactory<ComplianceTemporaryExceptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<ComplianceRequirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'compliance_requirement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isActiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->starts_at->lte($at) && $this->ends_at->gt($at);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveAt(Builder $query, CarbonInterface|string|null $at = null): Builder
    {
        $moment = $at instanceof CarbonInterface ? $at : ($at !== null ? Carbon::parse($at) : now());

        return $query
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>', $moment);
    }
}
