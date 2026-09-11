<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canMessage();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->canMessage() && $conversation->hasParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->canMessage();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
