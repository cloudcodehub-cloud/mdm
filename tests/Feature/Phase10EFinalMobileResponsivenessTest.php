<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10EFinalMobileResponsivenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_reports_and_documents_use_responsive_ops_patterns(): void
    {
        $reports = file_get_contents(resource_path('js/pages/reports/show.tsx'));
        $documents = file_get_contents(resource_path('js/pages/documents/index.tsx'));
        $viewer = file_get_contents(resource_path('js/pages/documents/viewer.tsx'));

        $this->assertNotFalse($reports);
        $this->assertNotFalse($documents);
        $this->assertNotFalse($viewer);

        $this->assertStringContainsString('ResponsiveOpsList', $reports);
        $this->assertStringContainsString('OpsRecordCard', $reports);
        $this->assertStringContainsString('page-shell', $reports);

        $this->assertStringContainsString('ResponsiveOpsList', $documents);
        $this->assertStringContainsString('OpsRecordCard', $documents);
        $this->assertStringContainsString('page-shell', $documents);

        $this->assertStringContainsString('min-h-10 w-full', $viewer);
    }

    public function test_agency_settings_mobile_markers_and_timezone_control(): void
    {
        $settings = file_get_contents(resource_path('js/pages/settings/general.tsx'));
        $timezone = file_get_contents(resource_path('js/components/mdm/timezone-search-select.tsx'));

        $this->assertNotFalse($settings);
        $this->assertNotFalse($timezone);

        $this->assertStringContainsString('page-shell', $settings);
        $this->assertStringContainsString('sticky-form-actions', $settings);
        $this->assertStringContainsString('Migrate future schedules', $settings);
        $this->assertStringContainsString('min-h-11', $timezone);
        $this->assertStringContainsString('break-words', $timezone);
        $this->assertStringContainsString('data-timezone-search', $timezone);
    }

    public function test_directory_support_and_dsp_surfaces_reuse_responsive_lists(): void
    {
        $employees = file_get_contents(resource_path('js/pages/employees/index.tsx'));
        $tickets = file_get_contents(resource_path('js/pages/support-tickets/index.tsx'));
        $compliance = file_get_contents(resource_path('js/pages/compliance/mine.tsx'));
        $availability = file_get_contents(resource_path('js/pages/availability/mine.tsx'));

        $this->assertNotFalse($employees);
        $this->assertNotFalse($tickets);
        $this->assertNotFalse($compliance);
        $this->assertNotFalse($availability);

        $this->assertStringContainsString('ResponsiveOpsList', $employees);
        $this->assertStringContainsString('ResponsiveOpsList', $tickets);
        $this->assertStringContainsString('ResponsiveOpsList', $compliance);
        $this->assertStringContainsString('page-shell', $availability);
    }

    public function test_authorized_roles_can_reach_reports_documents_and_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->has('reports')
                ->has('mobileNav.shortcuts', 3));

        $this->actingAs($admin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/index')
                ->has('documents.data')
                ->has('sidebarOpen'));

        $this->actingAs($admin)
            ->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/general')
                ->has('settings.timezone')
                ->has('timezoneOptions')
                ->has('scheduleMigrationPreview'));

        $this->actingAs($dsp)
            ->get(route('my-availability.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('availability/mine')
                ->has('weekly')
                ->has('mobileNav.home'));
    }

    public function test_support_tickets_remain_reachable_with_mobile_shell_props(): void
    {
        $user = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('support-tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('support-tickets/index')
                ->has('tickets.data')
                ->has('mobileNav.available'));
    }
}
