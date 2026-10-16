<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\EmployeeProfileChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property ReviewStatus $status
 * @property array<string, mixed> $proposed_values
 * @property array<string, mixed> $current_values
 * @property string|null $reason
 * @property int $requested_by_user_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $submitted_at
 */
#[Fillable([
    'employee_id',
    'status',
    'proposed_values',
    'current_values',
    'reason',
    'requested_by_user_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'submitted_at',
])]
class EmployeeProfileChangeRequest extends Model
{
    /** @use HasFactory<EmployeeProfileChangeRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'proposed_values' => 'array',
            'current_values' => 'array',
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
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Pending);
    }
}
