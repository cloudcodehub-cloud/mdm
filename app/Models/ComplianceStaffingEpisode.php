<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $grace_scheduled_visit_id
 * @property string|null $hard_stop_summary
 * @property Carbon $triggered_at
 * @property int|null $triggered_by_user_id
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_reason
 * @property Carbon|null $grace_consumed_at
 */
#[Fillable([
    'employee_id',
    'grace_scheduled_visit_id',
    'hard_stop_summary',
    'triggered_at',
    'triggered_by_user_id',
    'resolved_at',
    'resolved_reason',
    'grace_consumed_at',
])]
class ComplianceStaffingEpisode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'grace_consumed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<ScheduledVisit, $this>
     */
    public function graceVisit(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisit::class, 'grace_scheduled_visit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function graceConsumed(): bool
    {
        return $this->grace_consumed_at !== null;
    }
}
