<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementAudience;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements,
        private SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', Announcement::class);

        $audiences = $user->isSupervisor()
            ? [AnnouncementAudience::Dsps]
            : AnnouncementAudience::cases();

        return Inertia::render('announcements/index', [
            'announcements' => $this->announcements->serializeForUser($user),
            'audiences' => array_map(
                fn (AnnouncementAudience $audience): array => [
                    'value' => $audience->value,
                    'label' => $audience->label(),
                ],
                $audiences,
            ),
            'can' => [
                'create' => $user->can('create', Announcement::class),
            ],
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $publishedAt = $this->settings->parseLocalDateTime($request->string('published_at')->value())
            ?? $this->settings->now();
        $expiresAt = $this->settings->parseLocalDateTime($request->string('expires_at')->value());

        $this->announcements->publish($user, [
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'audience' => AnnouncementAudience::from($request->validated('audience')),
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement published.')]);

        return redirect()->route('announcements.index');
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->announcements->updateStatus($announcement, $request->boolean('is_active'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement updated.')]);

        return redirect()->route('announcements.index');
    }

    public function markRead(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('view', $announcement);

        $this->announcements->markRead($user, $announcement);

        return back();
    }
}
