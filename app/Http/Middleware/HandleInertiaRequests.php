<?php

namespace App\Http\Middleware;

use App\Services\InboxActivityService;
use App\Services\SettingsService;
use App\Services\VisitClockInService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'organization' => app(SettingsService::class)->shared(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'demoWeather' => [
                'source' => 'Local demo',
                'location' => 'Lakeside, OH',
                'condition' => 'Partly cloudy',
                'temperature' => '68°F',
            ],
            'inbox' => function () use ($user): array {
                if ($user === null) {
                    return [
                        'unread_messages' => 0,
                        'unread_notifications' => 0,
                        'notifications' => [],
                    ];
                }

                return app(InboxActivityService::class)->summary($user);
            },
            'activeWork' => function () use ($user): ?array {
                if ($user === null || ! $user->isDsp()) {
                    return null;
                }

                $user->loadMissing('employee');

                if ($user->employee === null) {
                    return null;
                }

                $visit = app(VisitClockInService::class)->activeVisitFor($user->employee);

                return $visit === null ? null : DirectoryPresenter::activeVisitSummary($visit);
            },
        ];
    }
}
