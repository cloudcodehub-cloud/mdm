<?php

namespace App\Models;

use App\Enums\AvailabilityRequestType;
use App\Enums\ReviewStatus;
use Database\Factories\DspAvailabilityRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $requested_by_user_id
 * @property AvailabilityRequestType $type
 * @property Carbon $effective_on
 * @property array<string, mixed> $payload
 * @property string|null $reason
 * @property ReviewStatus $status
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $submitted_at
 *
 * @property-read Employee $employee
 * @property-read User $requestedBy
 * @property-read User|null $reviewedBy
 *
 * @method static Builder<static> pending()
 */
#[Fillable([
    'employee_id',
    'requested_by_user_id',
    'type',
    'effective_on',
    'payload',
    'reason',
    'status',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'submitted_at',
])]
class DspAvailabilityRequest extends Model
{
    /** @use HasFactory<DspAvailabilityRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AvailabilityRequestType::class,
            'effective_on' => 'date',
            'payload' => 'array',
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
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Pending);
    }
}
