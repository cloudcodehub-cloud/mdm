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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read VisitTask|null $visitTask
 *
 * @method static Builder<static> visibleTo(User $user)
 */
#[Fillable([
    'visit_id',
    'visit_task_id',
    'type',
    'status',
    'message',
    'context',
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
