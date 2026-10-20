<?php

namespace App\Models;

use App\Enums\AdminMessageReviewPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Exceptional Admin non-participant message review audit (MDM 09 Decision 09-01).
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $admin_user_id
 * @property AdminMessageReviewPurpose $purpose
 * @property string $reason
 * @property Carbon $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $admin
 */
#[Fillable([
    'conversation_id',
    'admin_user_id',
    'purpose',
    'reason',
    'reviewed_at',
])]
class ConversationAdminReview extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AdminMessageReviewPurpose::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
