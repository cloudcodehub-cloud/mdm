<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Visit-specific, reasoned override of an approved DSP unavailable period
 * (approved leave or approved unavailable availability exception).
 *
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int $employee_id
 * @property string $conflict_kind
 * @property string $conflict_summary
 * @property string $override_reason
 * @property int|null $overridden_by_user_id
 * @property Carbon $overridden_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read Employee $employee
 * @property-read User|null $overriddenBy
 * @property-read User|null $acknowledgedBy
 */
#[Fillable([
    'scheduled_visit_id',
    'employee_id',
    'conflict_kind',
    'conflict_summary',
    'override_reason',
    'overridden_by_user_id',
    'overridden_at',
    'acknowledged_at',
    'acknowledged_by_user_id',
])]
class ScheduledVisitAvailabilityOverride extends Model
{
    public const KIND_LEAVE = 'leave';

    public const KIND_UNAVAILABLE = 'unavailable';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overridden_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ScheduledVisit, $this>
     */
    public function scheduledVisit(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisit::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }
}
