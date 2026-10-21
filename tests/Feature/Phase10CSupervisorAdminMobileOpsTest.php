<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10CSupervisorAdminMobileOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_responsive_ops_list_helper_is_present_for_mobile_cards(): void
    {
        $source = file_get_contents(
            resource_path('js/components/mdm/responsive-ops-list.tsx'),
        );

        $this->assertNotFalse($source);
        $this->assertStringContainsString('data-responsive-ops-cards', $source);
        $this->assertStringContainsString('data-responsive-ops-table', $source);
        $this->assertStringContainsString('md:hidden', $source);
        $this->assertStringContainsString('hidden overflow-x-auto md:block', $source);
    }

    public function test_supervisor_dashboard_and_operations_remain_scoped_and_reachable(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', 'SUPERVISOR')
                ->has('mobileNav.shortcuts', 3)
                ->where('mobileNav.shortcuts.0.key', 'scheduled-visits')
                ->where('mobileNav.shortcuts.1.key', 'incidents')
                ->where('mobileNav.shortcuts.2.key', 'messages'));

        $this->actingAs($supervisor)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->has('operations.metrics')
                ->has('operations.active_visits')
                ->has('mobileNav.home'));

        $this->actingAs($supervisor)
            ->get(route('visit-exceptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visit-exceptions/index')
                ->has('exceptions.data')
                ->has('mobileNav.shortcuts', 3));

        $this->actingAs($supervisor)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('incidents/index')
                ->has('incidents.data'));
    }

    public function test_admin_agency_operations_and_dashboard_remain_reachable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', 'ADMIN')
                ->where('mobileNav.shortcuts.0.key', 'operations')
                ->where('mobileNav.shortcuts.1.key', 'incidents')
                ->where('mobileNav.shortcuts.2.key', 'messages'));

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->has('operations.metrics')
                ->has('operations.staffing_needed_visits')
                ->has('sidebarOpen')
                ->has('mobileNav.more'));

        $this->actingAs($admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/index')
                ->has('records.data')
                ->has('mobileNav.home'));
    }

    public function test_dsp_cannot_open_operations_board(): void
    {
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_desktop_shell_markers_preserved_for_admin_ops(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sidebarOpen')
                ->has('mobileNav.home')
                ->has('mobileNav.shortcuts', 3)
                ->has('mobileNav.available'));
    }
}
