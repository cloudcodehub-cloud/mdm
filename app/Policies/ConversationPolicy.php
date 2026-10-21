<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationMessageAttachment;
use App\Models\User;
use App\Services\MessagingService;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canMessage();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if (! $user->canMessage() && ! $user->isAdmin()) {
            return false;
        }

        // Active or former participant (historical access).
        if ($conversation->wasParticipant($user)) {
            return true;
        }

        // Exceptional Admin review only after intentional audited grant in session.
        return app(MessagingService::class)->adminHasActiveReviewAccess($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->canMessage();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $user->canMessage() && $conversation->hasParticipant($user);
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        return $conversation->canManageMembership($user);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->isGroup() && $conversation->hasParticipant($user);
    }

    public function archive(User $user, Conversation $conversation): bool
    {
        return $user->canMessage() && $conversation->hasParticipant($user);
    }

    public function mute(User $user, Conversation $conversation): bool
    {
        return $user->canMessage() && $conversation->hasParticipant($user);
    }

    public function review(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin();
    }

    public function downloadAttachment(User $user, ConversationMessageAttachment $attachment): bool
    {
        $attachment->loadMissing('message.conversation');
        $conversation = $attachment->message?->conversation;

        if ($conversation === null) {
            return false;
        }

        if (! $this->view($user, $conversation)) {
            return false;
        }

        // Former participants may only download attachments from messages before they left.
        if ($conversation->wasParticipant($user) && ! $conversation->hasParticipant($user)) {
            $leftAt = $conversation->participantLeftAt($user);
            $createdAt = $attachment->message?->created_at;
            if ($leftAt !== null && $createdAt !== null && $createdAt->greaterThan($leftAt)) {
                return false;
            }
        }

        return true;
    }
}
