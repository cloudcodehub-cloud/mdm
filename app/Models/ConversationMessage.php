<?php

namespace App\Models;

use App\Enums\ConversationMessageType;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $sender_id
 * @property ConversationMessageType $message_type
 * @property string $body
 * @property array<string, mixed>|null $care_context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User|null $sender
 * @property-read Collection<int, ConversationMessageAttachment> $attachments
 */
#[Fillable(['conversation_id', 'sender_id', 'message_type', 'body', 'care_context'])]
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'care_context' => 'array',
            'message_type' => ConversationMessageType::class,
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<ConversationMessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationMessageAttachment::class);
    }

    public function isSystem(): bool
    {
        return $this->message_type === ConversationMessageType::System;
    }
}
