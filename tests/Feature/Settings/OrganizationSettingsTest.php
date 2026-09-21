<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\Employee;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Lakeside Supports',
            'timezone' => 'Pacific/Auckland',
            'date_format' => DateFormat::Iso->value,
            'time_format' => TimeFormat::TwentyFourHour->value,
            'first_day_of_week' => 1,
            'credential_expiring_soon_days' => 14,
        ], $overrides);
    }

    public function test_admin_can_view_and_update_organization_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('settings.general.edit'))
            ->assertOk();

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('Lakeside Supports', $settings->organization_name);
        $this->assertSame('Pacific/Auckland', $settings->timezone);
        $this->assertSame(DateFormat::Iso, $settings->date_format);
        $this->assertSame(TimeFormat::TwentyFourHour, $settings->time_format);
        $this->assertSame(1, $settings->first_day_of_week);
        $this->assertSame(14, $settings->credential_expiring_soon_days);
        $this->assertSame('Pacific/Auckland', app(SettingsService::class)->timezone());
    }

    public function test_non_admin_cannot_view_or_update_organization_settings(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('settings.general.edit'))
            ->assertForbidden();

        $this->actingAs($dsp)
            ->get(route('settings.general.edit'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->patch(route('settings.general.update'), $this->payload())
            ->assertForbidden();

        $this->actingAs($dsp)
            ->patch(route('settings.general.update'), $this->payload())
            ->assertForbidden();

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('MDM - Magic Data Management', $settings->organization_name);
        $this->assertSame('America/New_York', $settings->timezone);
    }

    public function test_settings_index_sends_admins_to_general(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertRedirect(route('settings.general.edit'));

        $this->actingAs($dsp)
            ->get(route('settings.index'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->payload([
                'timezone' => 'Not/A_Zone',
            ]))
            ->assertSessionHasErrors('timezone');
    }

    public function test_admin_can_upload_and_view_agency_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('agency.png', 80, 80);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->payload([
                'logo' => $file,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('local')->assertExists($settings->logo_path);

        $this->actingAs($admin)
            ->get(route('organization.logo'))
            ->assertOk();
    }
}
