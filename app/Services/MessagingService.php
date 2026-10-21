<?php

namespace App\Services;

use App\Enums\AdminMessageReviewPurpose;
use App\Enums\ConversationMessageType;
use App\Enums\ConversationType;
use App\Enums\InAppNotificationType;
use App\Models\Conversation;
use App\Models\ConversationAdminReview;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    public const AGENCY_MESSAGING_NOTICE = 'Agency Messaging Notice: Messages sent through MDM are not end-to-end encrypted and should not be considered private from your agency. Authorized Agency Admin may access and review messages when permitted for a legitimate agency purpose under agency policy and MDM authorization controls. Ordinary Supervisor status, caseload relationship, temporary Supervisor coverage, or management title does not grant access to unrelated private conversations.';

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
            ->where('type', ConversationType::Direct)
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
    public function startConversation(
        User $actor,
        User $recipient,
        string $body,
        array $context = [],
        ?UploadedFile $attachment = null,
    ): Conversation {
        $this->assertCanMessage($actor, $recipient);

        $conversation = DB::transaction(function () use ($actor, $recipient): Conversation {
            $key = Conversation::participantKeyFor($actor, $recipient);

            $conversation = Conversation::query()->firstOrCreate(
                ['participant_key' => $key],
                [
                    'organization_id' => null,
                    'type' => ConversationType::Direct,
                    'created_by_user_id' => $actor->id,
                ],
            );

            $this->attachParticipant($conversation, $actor);
            $this->attachParticipant($conversation, $recipient);

            return $conversation;
        });

        $this->sendMessage($actor, $conversation, $body, $context, $attachment);

        return $conversation->fresh(['participants', 'messages']) ?? $conversation;
    }

    /**
     * @param  list<int>  $participantIds
     */
    public function startGroup(User $actor, string $name, array $participantIds, string $body, ?UploadedFile $attachment = null): Conversation
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Private groups require a group name.',
            ]);
        }

        $ids = collect($participantIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $actor->id)
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Select at least one other participant.',
            ]);
        }

        $participants = User::query()
            ->activeForMessaging()
            ->whereIn('id', $ids)
            ->get();

        if ($participants->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'One or more selected users cannot be messaged.',
            ]);
        }

        $conversation = DB::transaction(function () use ($actor, $name, $participants): Conversation {
            $conversation = Conversation::query()->create([
                'organization_id' => null,
                'type' => ConversationType::Group,
                'name' => $name,
                'created_by_user_id' => $actor->id,
                'participant_key' => Conversation::groupParticipantKey(),
            ]);

            $this->attachParticipant($conversation, $actor);
            foreach ($participants as $participant) {
                $this->attachParticipant($conversation, $participant);
            }

            $this->recordSystemMessage(
                $conversation,
                $actor,
                $actor->name.' created the group "'.$name.'".',
            );

            return $conversation;
        });

        if (trim($body) !== '' || $attachment !== null) {
            $this->sendMessage($actor, $conversation, $body !== '' ? $body : 'Shared a file.', [], $attachment);
        }

        return $conversation->fresh(['participants', 'messages']) ?? $conversation;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendMessage(
        User $actor,
        Conversation $conversation,
        string $body,
        array $context = [],
        ?UploadedFile $attachment = null,
    ): ConversationMessage {
        $conversation->loadMissing('participants');

        if (! $conversation->hasParticipant($actor)) {
            abort(403);
        }

        $body = trim($body);
        if ($body === '' && $attachment === null) {
            throw ValidationException::withMessages([
                'body' => 'Enter a message or attach a file.',
            ]);
        }

        if ($conversation->isDirect()) {
            $recipient = $conversation->otherParticipant($actor);
            if ($recipient === null || ! $recipient->canMessage()) {
                throw ValidationException::withMessages([
                    'body' => __('This conversation is no longer available. Inactive accounts cannot receive messages.'),
                ]);
            }
        }

        $message = DB::transaction(function () use ($actor, $conversation, $body, $context, $attachment): ConversationMessage {
            $message = $conversation->messages()->create([
                'sender_id' => $actor->id,
                'message_type' => ConversationMessageType::User,
                'body' => $body === '' ? 'Shared a file.' : $body,
                'care_context' => $context === [] ? null : $context,
            ]);

            if ($attachment !== null) {
                $this->storeAttachment($message, $actor, $attachment);
            }

            $conversation->participants()->updateExistingPivot($actor->id, [
                'last_read_at' => now(),
            ]);

            // MDM 09-25 — a new message returns archived threads to the active inbox
            // for every active participant who had archived it (participant-specific).
            DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->whereNull('left_at')
                ->whereNotNull('archived_at')
                ->update(['archived_at' => null]);

            return $message->load('attachments');
        });

        $this->notifyActiveParticipants($conversation, $actor, $message);

        return $message;
    }

    /**
     * MDM 09-25 — archive/hide from this participant's inbox only.
     */
    public function archiveFor(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user)) {
            abort(403);
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'archived_at' => now(),
        ]);
    }

    /**
     * MDM 09-25 — restore from Archived view into the active inbox.
     */
    public function restoreFor(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user)) {
            abort(403);
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'archived_at' => null,
        ]);
    }

    /**
     * MDM 09-26 — mute ordinary message attention for this participant only.
     */
    public function muteFor(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user)) {
            abort(403);
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'muted_at' => now(),
        ]);
    }

    public function unmuteFor(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user)) {
            abort(403);
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'muted_at' => null,
        ]);
    }

    public function addParticipants(User $actor, Conversation $conversation, array $participantIds): Conversation
    {
        if (! $conversation->isGroup() || ! $conversation->canManageMembership($actor)) {
            abort(403);
        }

        $ids = collect($participantIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $users = User::query()->activeForMessaging()->whereIn('id', $ids)->get();

        DB::transaction(function () use ($actor, $conversation, $users): void {
            foreach ($users as $user) {
                if ($conversation->hasParticipant($user)) {
                    continue;
                }

                $existing = $conversation->participants()->where('users.id', $user->id)->first();
                if ($existing !== null && $existing->pivot->left_at !== null) {
                    $conversation->participants()->updateExistingPivot($user->id, [
                        'left_at' => null,
                        'joined_at' => now(),
                        'last_read_at' => null,
                    ]);
                } else {
                    $this->attachParticipant($conversation, $user);
                }

                $this->recordSystemMessage(
                    $conversation,
                    $actor,
                    $actor->name.' added '.$user->name.' to the group.',
                );
            }
        });

        return $conversation->fresh(['participants']) ?? $conversation;
    }

    public function removeParticipant(User $actor, Conversation $conversation, User $target): Conversation
    {
        if (! $conversation->isGroup() || ! $conversation->canManageMembership($actor)) {
            abort(403);
        }

        if (! $conversation->hasParticipant($target)) {
            throw ValidationException::withMessages([
                'user_id' => 'That user is not an active participant.',
            ]);
        }

        if ($target->id === $actor->id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'user_id' => 'Use leave to remove yourself from the group.',
            ]);
        }

        DB::transaction(function () use ($actor, $conversation, $target): void {
            $conversation->participants()->updateExistingPivot($target->id, [
                'left_at' => now(),
            ]);

            $this->recordSystemMessage(
                $conversation,
                $actor,
                $actor->name.' removed '.$target->name.' from the group.',
            );
        });

        return $conversation->fresh(['participants']) ?? $conversation;
    }

    public function leaveGroup(User $actor, Conversation $conversation): Conversation
    {
        if (! $conversation->isGroup() || ! $conversation->hasParticipant($actor)) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $conversation): void {
            $conversation->participants()->updateExistingPivot($actor->id, [
                'left_at' => now(),
            ]);

            $this->recordSystemMessage(
                $conversation,
                $actor,
                $actor->name.' left the group.',
            );

            if ($conversation->created_by_user_id === $actor->id) {
                // Management authority transfers to Admin; creator id retained for history.
                $this->recordSystemMessage(
                    $conversation,
                    $actor,
                    'Group management authority is now available to authorized Agency Admin.',
                );
            }
        });

        return $conversation->fresh(['participants']) ?? $conversation;
    }

    public function renameGroup(User $actor, Conversation $conversation, string $name): Conversation
    {
        if (! $conversation->isGroup() || ! $conversation->canManageMembership($actor)) {
            abort(403);
        }

        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Private groups require a group name.',
            ]);
        }

        $previous = $conversation->name;
        $conversation->forceFill(['name' => $name])->save();

        $this->recordSystemMessage(
            $conversation,
            $actor,
            $actor->name.' renamed the group from "'.$previous.'" to "'.$name.'".',
        );

        return $conversation->refresh();
    }

    public function recordAdminReview(
        User $admin,
        Conversation $conversation,
        AdminMessageReviewPurpose $purpose,
        string $reason,
    ): ConversationAdminReview {
        if (! $admin->isAdmin()) {
            abort(403);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for exceptional Admin message review.',
            ]);
        }

        $review = ConversationAdminReview::query()->create([
            'conversation_id' => $conversation->id,
            'admin_user_id' => $admin->id,
            'purpose' => $purpose,
            'reason' => $reason,
            'reviewed_at' => now(),
        ]);

        session()->put($this->adminReviewSessionKey($conversation), [
            'review_id' => $review->id,
            'at' => now()->timestamp,
        ]);

        return $review;
    }

    public function adminHasActiveReviewAccess(User $admin, Conversation $conversation): bool
    {
        if (! $admin->isAdmin()) {
            return false;
        }

        $payload = session()->get($this->adminReviewSessionKey($conversation));
        if (! is_array($payload) || ! isset($payload['at'])) {
            return false;
        }

        // Session-bound exceptional access (4 hours).
        return ((int) $payload['at']) >= now()->subHours(4)->timestamp;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchConversationsForAdminReview(User $admin, ?string $term = null): array
    {
        if (! $admin->isAdmin()) {
            return [];
        }

        $term = trim((string) $term);
        if ($term === '') {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $conversations = Conversation::query()
            ->with(['participants'])
            ->where(function ($query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhereHas('participants', function ($participants) use ($like): void {
                        $participants->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        return $this->values($conversations->map(fn (Conversation $conversation): array => [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'name' => $conversation->isGroup()
                ? $conversation->name
                : $conversation->participants->pluck('name')->join(' / '),
            'participant_names' => $conversation->participants->pluck('name')->values()->all(),
        ]));
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user)) {
            return;
        }

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
                    ->where('cp.user_id', '=', $user->id)
                    ->whereNull('cp.left_at')
                    ->whereNull('cp.archived_at');
            })
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->where('conversation_messages.message_type', ConversationMessageType::User->value)
            ->where(function ($query): void {
                $query->whereNull('cp.last_read_at')
                    ->orWhereColumn('conversation_messages.created_at', '>', 'cp.last_read_at');
            })
            ->count();
    }

    /**
     * @param  'inbox'|'archived'  $mailbox
     * @return list<array<string, mixed>>
     */
    public function conversationSummaries(User $user, string $mailbox = 'inbox'): array
    {
        $mailbox = $mailbox === 'archived' ? 'archived' : 'inbox';

        $conversations = Conversation::query()
            ->visibleTo($user)
            ->with(['participants', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->whereHas('participants', function ($query) use ($user, $mailbox): void {
                $query->where('users.id', $user->id);

                if ($mailbox === 'archived') {
                    // Archive applies only to active memberships.
                    $query->whereNull('conversation_participants.left_at')
                        ->whereNotNull('conversation_participants.archived_at');
                } else {
                    // Inbox: active non-archived threads, plus former-participant history.
                    $query->where(function ($inner): void {
                        $inner->whereNotNull('conversation_participants.left_at')
                            ->orWhereNull('conversation_participants.archived_at');
                    });
                }
            })
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
                    ->where('cp.user_id', '=', $user->id)
                    ->whereNull('cp.left_at');
            })
            ->where('conversation_messages.sender_id', '!=', $user->id)
            ->where('conversation_messages.message_type', ConversationMessageType::User->value)
            ->where(function ($query): void {
                $query->whereNull('cp.last_read_at')
                    ->orWhereColumn('conversation_messages.created_at', '>', 'cp.last_read_at');
            })
            ->groupBy('conversation_messages.conversation_id')
            ->pluck('unread_count', 'conversation_id');

        return $this->values($conversations->map(function (Conversation $conversation) use ($user, $unreadByConversation): array {
            $other = $conversation->otherParticipant($user);
            $latest = $conversation->messages->first();
            $active = $conversation->hasParticipant($user);
            $pivot = $conversation->participants->first(
                fn (User $participant): bool => $participant->id === $user->id,
            )?->pivot;

            return [
                'id' => $conversation->id,
                'type' => $conversation->type->value,
                'name' => $conversation->isGroup()
                    ? $conversation->name
                    : ($other?->name ?? 'Unknown user'),
                'other_user' => $other === null ? null : $this->serializeUser($other),
                'participant_count' => $conversation->participants->filter(
                    fn (User $participant): bool => $participant->pivot->left_at === null
                )->count(),
                'is_active_participant' => $active,
                'is_archived' => $pivot?->archived_at !== null,
                'is_muted' => $pivot?->muted_at !== null,
                'latest_message' => $latest === null ? null : $this->preview($latest->body),
                'latest_at' => $latest?->created_at !== null
                    ? $this->settings->formatDateTime($latest->created_at)
                    : null,
                'unread_count' => $active ? (int) ($unreadByConversation[$conversation->id] ?? 0) : 0,
            ];
        }));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeConversation(Conversation $conversation, User $viewer, bool $adminReview = false): array
    {
        $conversation->loadMissing(['participants', 'creator']);
        $other = $conversation->otherParticipant($viewer);
        $activeParticipants = $conversation->participants->filter(
            fn (User $participant): bool => $participant->pivot->left_at === null
        );

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'name' => $conversation->isGroup()
                ? $conversation->name
                : ($other?->name ?? 'Conversation'),
            'other_user' => $other === null ? null : $this->serializeUser($other),
            'participants' => $activeParticipants
                ->map(fn (User $participant): array => $this->serializeUser($participant))
                ->values()
                ->all(),
            'created_by_user_id' => $conversation->created_by_user_id,
            'can_manage' => $conversation->canManageMembership($viewer),
            'is_active_participant' => $conversation->hasParticipant($viewer),
            'is_archived' => $conversation->isArchivedFor($viewer),
            'is_muted' => $conversation->isMutedFor($viewer),
            'admin_review' => $adminReview,
            'first_unread_id' => $adminReview ? null : $this->firstUnreadMessageId($viewer, $conversation),
        ];
    }

    public function firstUnreadMessageId(User $user, Conversation $conversation): ?int
    {
        if (! $conversation->hasParticipant($user)) {
            return null;
        }

        $lastRead = $conversation->participants()
            ->where('users.id', $user->id)
            ->value('conversation_participants.last_read_at');

        $query = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('message_type', ConversationMessageType::User)
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
        $leftAt = $conversation->participantLeftAt($viewer);
        $query = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->orderBy('id')
            ->limit(200);

        // Former participants keep history only through departure (Decision 09-18 / 09-23).
        if ($leftAt !== null && ! $conversation->hasParticipant($viewer) && ! $this->adminHasActiveReviewAccess($viewer, $conversation)) {
            $query->where('created_at', '<=', $leftAt);
        }

        return $this->values($query->get()->map(fn (ConversationMessage $message): array => [
            'id' => $message->id,
            'body' => $message->body,
            'message_type' => $message->message_type->value,
            'is_system' => $message->isSystem(),
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?? 'System',
            'is_mine' => $message->sender_id === $viewer->id,
            'created_at' => $message->created_at !== null
                ? $this->settings->formatDateTime($message->created_at)
                : null,
            'created_on' => $message->created_at !== null
                ? $this->settings->formatDate($message->created_at)
                : null,
            'care_context' => $message->care_context,
            'attachments' => $message->attachments->map(fn (ConversationMessageAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ])->values()->all(),
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

    private function attachParticipant(Conversation $conversation, User $user): void
    {
        $conversation->participants()->syncWithoutDetaching([
            $user->id => [
                'joined_at' => now(),
                'left_at' => null,
            ],
        ]);
    }

    private function recordSystemMessage(Conversation $conversation, User $actor, string $body): ConversationMessage
    {
        return $conversation->messages()->create([
            'sender_id' => $actor->id,
            'message_type' => ConversationMessageType::System,
            'body' => $body,
            'care_context' => null,
        ]);
    }

    private function storeAttachment(
        ConversationMessage $message,
        User $uploader,
        UploadedFile $file,
    ): ConversationMessageAttachment {
        $path = $file->store('conversation-attachments/'.$message->conversation_id, 'local');

        return ConversationMessageAttachment::query()->create([
            'conversation_message_id' => $message->id,
            'uploaded_by_user_id' => $uploader->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    private function notifyActiveParticipants(
        Conversation $conversation,
        User $actor,
        ConversationMessage $message,
    ): void {
        $conversation->loadMissing('participants');

        foreach ($conversation->participants as $participant) {
            if ($participant->id === $actor->id) {
                continue;
            }

            if ($participant->pivot->left_at !== null) {
                continue;
            }

            if (! $participant->canMessage()) {
                continue;
            }

            // MDM 09-26 — mute suppresses ordinary message attention only.
            if ($participant->pivot->muted_at !== null) {
                continue;
            }

            $title = $conversation->isGroup()
                ? ($conversation->name ?? 'Group').' · '.$actor->name
                : $actor->name;

            $this->notifications->notify(
                $participant,
                InAppNotificationType::Message,
                $title,
                $this->preview($message->body),
                'message:'.$message->id.':'.$participant->id,
                route('messages.show', $conversation),
            );
        }
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

    private function adminReviewSessionKey(Conversation $conversation): string
    {
        return 'admin_message_review.'.$conversation->id;
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
