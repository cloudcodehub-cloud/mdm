<?php

namespace App\Models;

use App\Enums\VisitSafetyCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $sequence
 * @property Carbon $due_at
 * @property VisitSafetyCheckStatus $status
 * @property Carbon|null $completed_at
 * @property string|null $outcome_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read VisitException|null $exception
 */
#[Fillable([
    'visit_id',
    'sequence',
    'due_at',
    'status',
    'completed_at',
    'outcome_reason',
])]
class VisitSafetyCheck extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => VisitSafetyCheckStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return HasOne<VisitException, $this>
     */
    public function exception(): HasOne
    {
        return $this->hasOne(VisitException::class);
    }

    public function isDocumented(): bool
    {
        if ($this->status === VisitSafetyCheckStatus::Completed) {
            return true;
        }

        if ($this->status === VisitSafetyCheckStatus::Late) {
            return $this->completed_at !== null && filled($this->outcome_reason);
        }

        if ($this->status === VisitSafetyCheckStatus::Missed) {
            return filled($this->outcome_reason);
        }

        return false;
    }
}
