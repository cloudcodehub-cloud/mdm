<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\OrganizationSettingChangeKey;
use App\Enums\TimeFormat;
use App\Models\OrganizationSetting;
use App\Models\OrganizationSettingChangeEvent;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\TimezoneOptionSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9GSearchableTimezoneSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Timezone Search',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Harbor Timezone Search',
            'display_name' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'United States',
            'main_phone' => '',
            'main_email' => '',
            'website' => '',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
            'incident_acknowledgment_minutes' => 60,
            'staffing_needed_urgency_hours' => 24,
        ], $overrides);
    }

    public function test_settings_page_exposes_searchable_iana_timezone_options(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('settings.general.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/general')
                ->has('timezoneOptions')
                ->where('settings.timezone', 'America/New_York')
                ->where(
                    'timezoneOptions',
                    fn (mixed $options): bool => collect($options)->contains(
                        fn (mixed $option): bool => is_array($option)
                            && ($option['value'] ?? null) === 'America/Chicago'
                            && filled($option['label'] ?? null)
                            && filled($option['group'] ?? null),
                    ) && collect($options)->contains(
                        fn (mixed $option): bool => is_array($option)
                            && ($option['value'] ?? null) === 'America/New_York',
                    ) && collect($options)->every(
                        fn (mixed $option): bool => is_array($option)
                            && is_string($option['value'] ?? null)
                            && ($option['value'] ?? '') !== ''
                            && filled($option['label'] ?? null),
                    ),
                ));

        $options = app(SettingsService::class)->timezoneOptions();
        $filtered = TimezoneOptionSearch::filter($options, 'los angeles');
        $this->assertNotEmpty($filtered);
        $this->assertTrue(collect($filtered)->contains(
            fn (array $option): bool => $option['value'] === 'America/Los_Angeles',
        ));
        $this->assertSame([], TimezoneOptionSearch::filter($options, 'definitely-not-a-zone-xyz'));
    }

    public function test_canonical_iana_value_is_accepted_and_invalid_timezone_is_rejected(): void
    {
        Carbon::setTestNow('2026-10-22 15:00:00');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'timezone' => 'Not/A_Real_Zone',
                'timezone_effective_at' => '2026-10-23T09:00',
                'timezone_change_reason' => 'Bad zone',
                'confirm_timezone_change' => '1',
            ]))
            ->assertSessionHasErrors('timezone');

        $this->assertSame('America/New_York', app(SettingsService::class)->timezone());

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'timezone' => 'America/Los_Angeles',
                'timezone_effective_at' => '2026-10-23T09:00',
                'timezone_change_reason' => 'West coast operations',
                'confirm_timezone_change' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('America/New_York', $settings->timezone);
        $this->assertSame('America/Los_Angeles', $settings->pending_timezone);

        $event = OrganizationSettingChangeEvent::query()
            ->where('setting_key', OrganizationSettingChangeKey::AgencyOperationalTimezone)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('America/Los_Angeles', $event->new_value);
        $this->assertSame('West coast operations', $event->reason);
    }
}
