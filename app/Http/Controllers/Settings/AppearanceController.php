<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Appearance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('settings/appearance');
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $appearance = Appearance::from($request->validated('appearance'));
        $this->settings->updateAppearance($request->user(), $appearance);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appearance updated.')]);

        return back();
    }
}
