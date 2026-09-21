<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function searchableUsers(User $actor, ?string $term = null): Collection
    {
        $query = User::query()
            ->activeForMessaging()
            ->where('id', '!=', $actor->id)
            ->orderBy('name');

        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        }

        return $query->limit(25)->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function previewFor(User $actor, User $recipient): array
    {
        $this->assertCanMessage($actor, $recipient);

        $conversation = Conversation::query()
            ->where('participant_key', Conversation::participantKeyFor($actor, $recipient))
            ->first();

        return [
            'recipient' => $this->serializeContact($recipient),
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id,
            ],
            'messages' => $conversation === null ? [] : $this->messagesFor($conversation, $actor),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function startConversation(User $actor, User $recipient, string $body, array $context = []): Conversation
    {
        $this->assertCanMessage($actor, $recipient);

        $conversation = DB::transaction(function () use ($actor, $recipient): Conversation {
            $key = Conversation::participantKeyFor($actor, $recipient);

            $conversation = Conversation::query()->firstOrCreate(
                ['participant_key' => $key],
                ['organization_id' => null],
            );

            $conversation->participants()->syncWithoutDetaching([$actor->id, $recipient->id]);

            return $conversation;
        });

        $this->sendMessage($actor, $conversation, $body, $context);

        return $conversation->fresh(['participants', 'messages']) ?? $conversation;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendMessage(User $actor, Conversation $conversation, string $body, array $context = []): ConversationMessage
    {
        $conversation->loadMissing('participants');

        if (! $conversation->hasParticipant($actor)) {
            abort(403);
        }

        $recipient = $conversation->otherParticipant($actor);

        if ($recipient === null || ! $recipient->canMessage()) {
            throw ValidationException::withMessages([
                'body' => __('This conversation is no longer available. Inactive accounts cannot receive messages.'),
            ]);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $actor->id,
            'body' => $body,
            'care_context' => $context === [] ? null : $context,
        ]);

        $conversation->participants()->updateExistingPivot($actor->id, [
            'last_read_at' => now(),
        ]);

        $this->notifications->notify(
            $recipient,
            InAppNotificationType::Message,
            $actor->name,
            $this->preview($body),
            'message:'.$message->id,
            route('messages.show', $conversation),
        );

        return $message;
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);

        $this->notifications->markUrlRead($user, route('messages.show', $conversation));
    }

    public function unreadCount(User $user): int
    {
        return (int) ConversationMessage::query()
            ->join('conversation_participants as cp', function ($join) use ($user): void {
                $join->on('cp.conversation_id', '=', 'conversation_messages.conversation_id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->where(function ($query): void {
                $query->whereNull('cp.last_read_at')
                    ->orWhereColumn('conversation_messages.created_at', '>', 'cp.last_read_at');
            })
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conversationSummaries(User $user): array
    {
        $conversations = Conversation::query()
            ->visibleTo($user)
            ->with(['participants', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->get()
            ->sortByDesc(function (Conversation $conversation): string {
                $latest = $conversation->messages->first();

                return $latest?->created_at?->toIso8601String() ?? $conversation->created_at?->toIso8601String() ?? '';
            })
            ->values();

        $unreadByConversation = ConversationMessage::query()
            ->selectRaw('conversation_messages.conversation_id, count(*) as unread_count')
            ->join('conversation_participants as cp', function ($join) use ($user): void {
                $join->on('cp.conversation_id', '=', 'conversation_messages.conversation_id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->where(function ($query): void {
                $query->whereNull('cp.last_read_at')
                    ->orWhereColumn('conversation_messages.created_at', '>', 'cp.last_read_at');
            })
            ->groupBy('conversation_messages.conversation_id')
            ->pluck('unread_count', 'conversation_id');

        return $this->values($conversations->map(function (Conversation $conversation) use ($user, $unreadByConversation): array {
            $other = $conversation->otherParticipant($user);
            $latest = $conversation->messages->first();

            return [
                'id' => $conversation->id,
                'other_user' => $other === null ? null : $this->serializeUser($other),
                'latest_message' => $latest === null ? null : $this->preview($latest->body),
                'latest_at' => $latest?->created_at !== null
                    ? $this->settings->formatDateTime($latest->created_at)
                    : null,
                'unread_count' => (int) ($unreadByConversation[$conversation->id] ?? 0),
            ];
        }));
    }

    public function firstUnreadMessageId(User $user, Conversation $conversation): ?int
    {
        $lastRead = $conversation->participants()
            ->where('users.id', $user->id)
            ->value('conversation_participants.last_read_at');

        $query = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->orderBy('id');

        if (filled($lastRead)) {
            $query->where('created_at', '>', $lastRead);
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesFor(Conversation $conversation, User $viewer): array
    {
        return $this->values($conversation->messages()
            ->with('sender')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (ConversationMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name,
                'is_mine' => $message->sender_id === $viewer->id,
                'created_at' => $message->created_at !== null
                    ? $this->settings->formatDateTime($message->created_at)
                    : null,
                'created_on' => $message->created_at !== null
                    ? $this->settings->formatDate($message->created_at)
                    : null,
                'care_context' => $message->care_context,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUser(User $user): array
    {
        $user->loadMissing('employee');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'can_message' => $user->canMessage(),
            'photo_url' => $user->employee
                ? app(ProfilePhotoService::class)->employeeUrl($user->employee)
                : null,
            'initials' => $user->employee?->initials(),
        ];
    }

    /**
     * Limited contact payload for in-visit messaging previews.
     *
     * @return array{id: int, name: string, role: string}
     */
    public function serializeContact(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
        ];
    }

    public function preview(string $body, int $limit = 80): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $limit - 1).'…';
    }

    private function assertCanMessage(User $actor, User $recipient): void
    {
        if ($actor->is($recipient)) {
            throw ValidationException::withMessages([
                'user_id' => __('You cannot start a conversation with yourself.'),
            ]);
        }

        if (! $recipient->canMessage()) {
            throw ValidationException::withMessages([
                'user_id' => __('Inactive or terminated accounts cannot be messaged.'),
            ]);
        }
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private function values(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }
}
