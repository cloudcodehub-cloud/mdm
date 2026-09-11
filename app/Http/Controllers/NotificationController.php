<?php

namespace App\Http\Controllers;

use App\Enums\InAppNotificationType;
use App\Models\Announcement;
use App\Models\InAppNotification;
use App\Services\AnnouncementService;
use App\Services\InAppNotificationService;
use App\Services\InboxActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function activity(Request $request, InboxActivityService $inbox): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $after = $request->query('after');
        $afterAt = null;

        if (is_string($after) && $after !== '') {
            try {
                $afterAt = Carbon::parse($after);
            } catch (\Throwable) {
                $afterAt = null;
            }
        }

        return response()->json($inbox->poll($user, $afterAt));
    }

    public function markRead(
        Request $request,
        InAppNotification $notification,
        InAppNotificationService $notifications,
        AnnouncementService $announcements,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('update', $notification);

        $notifications->markRead($notification);

        if ($notification->type === InAppNotificationType::Announcement) {
            $announcementId = (int) str_replace('announcement:', '', $notification->source_key);
            $announcement = Announcement::query()->find($announcementId);

            if ($announcement instanceof Announcement && $user->can('view', $announcement)) {
                $announcements->markRead($user, $announcement);
            }
        }

        $url = $notification->url;

        if (is_string($url) && $url !== '') {
            return redirect()->to($url);
        }

        return back();
    }

    public function markAllRead(Request $request, InAppNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $notifications->markAllRead($user);

        return back();
    }
}
