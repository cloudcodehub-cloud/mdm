<?php

namespace App\Models;

use App\Enums\AttendanceCorrectionStatus;
use Database\Factories\AttendanceCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int|null $visit_id
 * @property AttendanceCorrectionStatus $status
 * @property Carbon|null $original_clocked_in_at
 * @property Carbon|null $original_clocked_out_at
 * @property Carbon|null $requested_clocked_in_at
 * @property Carbon|null $requested_clocked_out_at
 * @property string $reason
 * @property string|null $note
 * @property int $requested_by_user_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read Visit|null $visit
 * @property-read User $requestedBy
 * @property-read User|null $reviewedBy
 *
 * @method static Builder<static> pending()
 * @method static Builder<static> approved()
 */
#[Fillable([
    'scheduled_visit_id',
    'visit_id',
    'status',
    'original_clocked_in_at',
    'original_clocked_out_at',
    'requested_clocked_in_at',
    'requested_clocked_out_at',
    'reason',
    'note',
    'requested_by_user_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
])]
class AttendanceCorrection extends Model
{
    /** @use HasFactory<AttendanceCorrectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceCorrectionStatus::class,
            'original_clocked_in_at' => 'datetime',
            'original_clocked_out_at' => 'datetime',
            'requested_clocked_in_at' => 'datetime',
            'requested_clocked_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AttendanceCorrectionStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AttendanceCorrectionStatus::Approved);
    }
}
