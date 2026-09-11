<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\EmployeeTimeOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string|null $reason
 * @property ReviewStatus $status
 * @property int $requested_by_user_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $submitted_at
 *
 * @property-read Employee $employee
 * @property-read User $requestedBy
 * @property-read User|null $reviewedBy
 *
 * @method static Builder<static> approved()
 * @method static Builder<static> pending()
 */
#[Fillable([
    'employee_id',
    'starts_on',
    'ends_on',
    'starts_at',
    'ends_at',
    'reason',
    'status',
    'requested_by_user_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'submitted_at',
])]
class EmployeeTimeOff extends Model
{
    /** @use HasFactory<EmployeeTimeOffFactory> */
    use HasFactory;

    protected $table = 'employee_time_off';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Pending);
    }
}
