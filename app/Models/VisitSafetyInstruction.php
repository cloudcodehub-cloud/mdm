<?php

namespace App\Models;

use Database\Factories\VisitSafetyInstructionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property string $content
 * @property bool $is_urgent
 * @property int $created_by_user_id
 * @property Carbon $effective_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read User $createdBy
 * @property-read User|null $acknowledgedBy
 */
#[Fillable([
    'visit_id',
    'content',
    'is_urgent',
    'created_by_user_id',
    'effective_at',
    'acknowledged_at',
    'acknowledged_by_user_id',
])]
class VisitSafetyInstruction extends Model
{
    /** @use HasFactory<VisitSafetyInstructionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'effective_at' => 'datetime',
            'acknowledged_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
}
