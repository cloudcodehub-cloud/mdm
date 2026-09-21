<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\Employee;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureOrganization(array $overrides = []): SettingsService
    {
        $service = app(SettingsService::class);
        $service->updateOrganization(array_merge([
            'organization_name' => 'Harbor Supports',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ], $overrides));

        return $service;
    }

    public function test_greeting_uses_organization_local_morning_afternoon_and_evening(): void
    {
        $settings = $this->configureOrganization(['timezone' => 'America/New_York']);

        $this->assertSame(
            'Good morning',
            $settings->greeting(Carbon::parse('2026-09-11 09:00:00', 'UTC')),
        );
        $this->assertSame(
            'Good morning',
            $settings->greeting(Carbon::parse('2026-09-11 15:59:00', 'UTC')),
        );
        $this->assertSame(
            'Good afternoon',
            $settings->greeting(Carbon::parse('2026-09-11 16:00:00', 'UTC')),
        );
        $this->assertSame(
            'Good afternoon',
            $settings->greeting(Carbon::parse('2026-09-11 20:59:00', 'UTC')),
        );
        $this->assertSame(
            'Good evening',
            $settings->greeting(Carbon::parse('2026-09-11 21:00:00', 'UTC')),
        );
        $this->assertSame(
            'Good evening',
            $settings->greeting(Carbon::parse('2026-09-11 08:59:00', 'UTC')),
        );
    }

    public function test_timezone_change_shifts_greeting_and_daytime_context(): void
    {
        $instant = Carbon::parse('2026-09-11 16:00:00', 'UTC');

        $this->configureOrganization(['timezone' => 'America/New_York']);
        $this->assertSame('Good afternoon', app(SettingsService::class)->greeting($instant));
        $this->assertTrue(app(SettingsService::class)->isDaytime($instant));

        $this->configureOrganization(['timezone' => 'America/Los_Angeles']);
        $this->assertSame('Good morning', app(SettingsService::class)->greeting($instant));
        $this->assertTrue(app(SettingsService::class)->isDaytime($instant));

        $this->configureOrganization(['timezone' => 'Pacific/Auckland']);
        $this->assertSame('Good evening', app(SettingsService::class)->greeting($instant));
        $this->assertFalse(app(SettingsService::class)->isDaytime($instant));
    }

    public function test_agency_name_fallback_and_shared_dashboard_context(): void
    {
        OrganizationSetting::query()->update(['organization_name' => '   ']);
        app()->forgetInstance(SettingsService::class);

        $this->assertSame('Magic Data Management', app(SettingsService::class)->agencyName());

        $this->configureOrganization([
            'organization_name' => 'Harbor Supports',
            'timezone' => 'America/Chicago',
        ]);

        Carbon::setTestNow('2026-09-11 14:00:00');
        $admin = User::factory()->admin()->create(['name' => 'Avery Quinn']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.organization_name', 'Harbor Supports')
                ->where('organization.timezone', 'America/Chicago')
                ->where(
                    'organization.now',
                    fn (mixed $value): bool => is_string($value) && str_starts_with($value, '2026-09-11T14:00:00'),
                )
                ->where('dashboard.greeting_name', 'Avery Quinn')
            );
    }

    public function test_admin_can_update_agency_name_and_timezone_while_other_roles_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $payload = [
            'organization_name' => 'Ultimate Care Supported Living',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ];

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $this->assertSame('Ultimate Care Supported Living', OrganizationSetting::query()->value('organization_name'));
        $this->assertSame('America/New_York', OrganizationSetting::query()->value('timezone'));

        $blocked = array_merge($payload, [
            'organization_name' => 'Should not save',
            'timezone' => 'UTC',
        ]);

        $this->actingAs($supervisor)
            ->patch(route('settings.general.update'), $blocked)
            ->assertForbidden();

        $this->actingAs($dsp)
            ->patch(route('settings.general.update'), $blocked)
            ->assertForbidden();

        $this->assertSame('Ultimate Care Supported Living', OrganizationSetting::query()->value('organization_name'));
        $this->assertSame('America/New_York', OrganizationSetting::query()->value('timezone'));
    }
}
