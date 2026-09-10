<?php

namespace App\Models;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use Database\Factories\VisitExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int|null $visit_task_id
 * @property VisitExceptionType $type
 * @property VisitExceptionStatus $status
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_notes
 * @property list<array<string, mixed>>|null $status_history
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read VisitTask|null $visitTask
 * @property-read User|null $reviewedBy
 * @property-read User|null $resolvedBy
 *
 * @method static Builder<static> visibleTo(User $user)
 * @method static Builder<static> open()
 * @method static Builder<static> unresolved()
 */
#[Fillable([
    'visit_id',
    'visit_task_id',
    'type',
    'status',
    'message',
    'context',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_notes',
    'resolved_by_user_id',
    'resolved_at',
    'resolution_notes',
    'status_history',
])]
class VisitException extends Model
{
    /** @use HasFactory<VisitExceptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VisitExceptionType::class,
            'status' => VisitExceptionStatus::class,
            'context' => 'array',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'status_history' => 'array',
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
     * @return BelongsTo<VisitTask, $this>
     */
    public function visitTask(): BelongsTo
    {
        return $this->belongsTo(VisitTask::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === VisitExceptionStatus::Open;
    }

    public function isResolved(): bool
    {
        return $this->status === VisitExceptionStatus::Resolved;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', VisitExceptionStatus::Open);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            VisitExceptionStatus::Open,
            VisitExceptionStatus::Reviewed,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn(
            'visit_id',
            Visit::query()->visibleTo($user)->select('id'),
        );
    }
}
