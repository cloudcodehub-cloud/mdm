<?php

namespace App\Policies;

use App\Models\ConversationMessageAttachment;
use App\Models\User;

class ConversationMessageAttachmentPolicy
{
    public function view(User $user, ConversationMessageAttachment $attachment): bool
    {
        return app(ConversationPolicy::class)->downloadAttachment($user, $attachment);
    }
}
