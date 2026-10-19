<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $visit_id
 * @property string $field_key
 * @property string|null $previous_content
 * @property string|null $new_content
 * @property int $actor_user_id
 * @property string|null $reason
 * @property-read Visit $visit
 * @property-read User $actor
 */
#[Fillable([
    'visit_id',
    'field_key',
    'previous_content',
    'new_content',
    'actor_user_id',
    'reason',
])]
class VisitNoteHistoryEvent extends Model
{
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
