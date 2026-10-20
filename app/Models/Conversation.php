<?php

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property ConversationType $type
 * @property string|null $name
 * @property int|null $created_by_user_id
 * @property string $participant_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, User> $participants
 * @property-read Collection<int, ConversationMessage> $messages
 * @property-read Collection<int, ConversationAdminReview> $adminReviews
 *
 * @method static Builder<static> visibleTo(User $user)
 */
#[Fillable(['organization_id', 'type', 'name', 'created_by_user_id', 'participant_key'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * @return HasMany<ConversationAdminReview, $this>
     */
    public function adminReviews(): HasMany
    {
        return $this->hasMany(ConversationAdminReview::class);
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    public function isDirect(): bool
    {
        return $this->type === ConversationType::Direct;
    }

    public function otherParticipant(User $user): ?User
    {
        if ($this->isGroup()) {
            return null;
        }

        $this->loadMissing('participants');

        return $this->participants->first(fn (User $participant): bool => $participant->id !== $user->id);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()
            ->where('users.id', $user->id)
            ->whereNull('conversation_participants.left_at')
            ->exists();
    }

    public function wasParticipant(User $user): bool
    {
        return $this->participants()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function participantLeftAt(User $user): ?Carbon
    {
        $leftAt = $this->participants()
            ->where('users.id', $user->id)
            ->value('conversation_participants.left_at');

        return $leftAt === null ? null : Carbon::parse($leftAt);
    }

    public function canManageMembership(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->isGroup()
            && $this->created_by_user_id === $user->id
            && $this->hasParticipant($user);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $participants) => $participants->where('users.id', $user->id),
        );
    }

    public static function participantKeyFor(User $first, User $second): string
    {
        $ids = [$first->id, $second->id];
        sort($ids);

        return $ids[0].':'.$ids[1];
    }

    public static function groupParticipantKey(): string
    {
        return 'group:'.Str::uuid()->toString();
    }
}
