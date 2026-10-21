<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\TimeFormat;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10BDspMobileVisitLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sticky_form_actions_clear_phone_bottom_navigation(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('@utility sticky-form-actions', $css);
        $this->assertStringContainsString(
            'bottom: calc(4rem + env(safe-area-inset-bottom, 0px));',
            $css,
        );
        $this->assertStringContainsString('@media (min-width: 768px)', $css);
        $this->assertStringContainsString('.sticky-form-actions', $css);
        $this->assertStringContainsString('bottom: 0;', $css);
    }

    public function test_dsp_today_and_visits_surfaces_remain_reachable(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);

        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('mobileNav.home')
                ->has('mobileNav.shortcuts', 3));

        $this->actingAs($user)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/index')
                ->has('visits.data')
                ->has('mobileNav.shortcuts', 3));
    }

    public function test_active_visit_exposes_mobile_workflow_actions_and_shell_props(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
                'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
            ])
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.id', $visit->id)
                ->where('visit.status', 'in_progress')
                ->where('can.clock_out', true)
                ->where('can.record_tasks', true)
                ->where('can.report_incident', true)
                ->has('mobileNav.home')
                ->has('mobileNav.shortcuts', 3)
                ->has('activeWork')
                ->where('activeWork.id', $visit->id));
    }

    public function test_desktop_shell_markers_remain_with_mobile_nav_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sidebarOpen')
                ->has('mobileNav.home')
                ->has('mobileNav.shortcuts', 3)
                ->has('mobileNav.more'));
    }

    public function test_completed_visit_summary_exposes_correction_path_for_dsp(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
                'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
            ])
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();

        Carbon::setTestNow('2026-09-11 10:00:00');

        $this->actingAs($user)
            ->post(route('visits.clock-out', $visit), [
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
                'latitude' => 40.1,
                'longitude' => -82.2,
                'accuracy' => 10,
                'handover_note' => 'Handover complete for mobile summary check.',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.status', 'completed')
                ->where('can.request_correction', true)
                ->has('mobileNav.shortcuts', 3));
    }
}
