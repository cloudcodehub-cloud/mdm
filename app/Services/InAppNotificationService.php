<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;

class InAppNotificationService
{
    public function notify(
        User $recipient,
        InAppNotificationType $type,
        string $title,
        string $body,
        string $sourceKey,
        ?string $url = null,
    ): InAppNotification {
        return InAppNotification::query()->updateOrCreate(
            [
                'user_id' => $recipient->id,
                'source_key' => $sourceKey,
            ],
            [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ],
        );
    }

    public function markRead(InAppNotification $notification): void
    {
        $notification->markRead();
    }

    public function markAllRead(User $user): void
    {
        InAppNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function markUrlRead(User $user, string $url): void
    {
        InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('url', $url)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentFor(User $user, int $limit = 12): array
    {
        return $this->values(
            InAppNotification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (InAppNotification $notification): array => $this->serialize($notification)),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toastsSince(User $user, Carbon $after): array
    {
        return $this->values(
            InAppNotification::query()
                ->where('user_id', $user->id)
                ->unread()
                ->where('created_at', '>', $after)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (InAppNotification $notification): array => $this->serialize($notification)),
        );
    }

    public function unreadCount(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(InAppNotification $notification): array
    {
        $settings = app(SettingsService::class);

        return [
            'id' => $notification->id,
            'type' => $notification->type->value,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'source_key' => $notification->source_key,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at !== null
                ? $settings->formatDateTime($notification->created_at)
                : null,
        ];
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
