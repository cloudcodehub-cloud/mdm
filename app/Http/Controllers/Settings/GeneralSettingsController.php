<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrganizationSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function edit(): Response
    {
        $this->authorize('update', $this->settings->current());

        return Inertia::render('settings/general', [
            'settings' => [
                'organization_name' => $this->settings->current()->organization_name,
                'timezone' => $this->settings->timezone(),
                'date_format' => $this->settings->current()->date_format->value,
                'time_format' => $this->settings->current()->time_format->value,
                'first_day_of_week' => $this->settings->current()->first_day_of_week,
                'credential_expiring_soon_days' => $this->settings->credentialExpiringSoonDays(),
            ],
            'timezoneOptions' => $this->settings->timezoneOptions(),
            'dateFormatOptions' => $this->settings->dateFormatOptions(),
            'timeFormatOptions' => $this->settings->timeFormatOptions(),
            'firstDayOfWeekOptions' => $this->settings->firstDayOfWeekOptions(),
        ]);
    }

    public function update(UpdateOrganizationSettingsRequest $request): RedirectResponse
    {
        $this->settings->updateOrganization($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization settings saved.')]);

        return to_route('settings.general.edit');
    }
}
