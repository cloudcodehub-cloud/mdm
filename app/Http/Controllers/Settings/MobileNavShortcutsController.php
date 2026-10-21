<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMobileNavShortcutsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MobileNavShortcutsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function update(UpdateMobileNavShortcutsRequest $request): RedirectResponse
    {
        $this->settings->updateMobileNavShortcuts(
            $request->user(),
            $request->shortcutKeys(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Mobile shortcuts updated.'),
        ]);

        return back();
    }
}
