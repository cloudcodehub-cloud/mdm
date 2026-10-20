<?php

namespace App\Models;

use App\Enums\TemporarySupervisorCoverageMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $original_supervisor_id
 * @property int $covering_supervisor_id
 * @property TemporarySupervisorCoverageMode $mode
 * @property list<int>|null $client_ids
 * @property list<int>|null $employee_ids
 * @property list<int>|null $deferred_remove_client_ids
 * @property list<int>|null $deferred_remove_employee_ids
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $revoke_reason
 * @property string|null $note
 * @property int $created_by_user_id
 */
class TemporarySupervisorCoverage extends Model
{
    protected $fillable = [
        'original_supervisor_id',
        'covering_supervisor_id',
        'mode',
        'client_ids',
        'employee_ids',
        'deferred_remove_client_ids',
        'deferred_remove_employee_ids',
        'starts_at',
        'ends_at',
        'revoked_at',
        'revoked_by_user_id',
        'revoke_reason',
        'note',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => TemporarySupervisorCoverageMode::class,
            'client_ids' => 'array',
            'employee_ids' => 'array',
            'deferred_remove_client_ids' => 'array',
            'deferred_remove_employee_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(?Carbon $now = null): bool
    {
        $now ??= now();

        return $now->gt($this->ends_at);
    }

    public function isCurrentlyActive(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->isRevoked()) {
            return false;
        }

        return $now->gte($this->starts_at) && $now->lte($this->ends_at);
    }

    public function statusLabel(?Carbon $now = null): string
    {
        if ($this->isRevoked()) {
            return 'Revoked';
        }

        $now ??= now();

        if ($now->lt($this->starts_at)) {
            return 'Scheduled';
        }

        if ($this->isExpired($now)) {
            return 'Expired';
        }

        return 'Active';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function originalSupervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'original_supervisor_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function coveringSupervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'covering_supervisor_id');
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

    /**
     * @return HasMany<TemporarySupervisorCoverageAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(TemporarySupervisorCoverageAudit::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyActive(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }
}
