<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class InboxActivityService
{
    public function __construct(
        private MessagingService $messaging,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        return [
            'unread_messages' => $this->messaging->unreadCount($user),
            'unread_notifications' => $this->notifications->unreadCount($user),
            'notifications' => $this->notifications->recentFor($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function poll(User $user, ?Carbon $after = null): array
    {
        return [
            ...$this->summary($user),
            'toasts' => $after instanceof Carbon
                ? $this->notifications->toastsSince($user, $after)
                : [],
        ];
    }
}
