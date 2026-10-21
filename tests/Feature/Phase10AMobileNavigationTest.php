<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Support\MobileNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10AMobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_shared_mobile_nav_uses_role_specific_defaults(): void
    {
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($dsp)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('mobileNav', fn (Assert $nav) => $nav
                    ->where('home.key', 'dashboard')
                    ->where('defaults', MobileNavigation::defaultsFor(Role::Dsp))
                    ->has('shortcuts', 3)
                    ->where('shortcuts.0.key', 'scheduled-visits')
                    ->where('shortcuts.1.key', 'my-availability')
                    ->where('shortcuts.2.key', 'messages')
                    ->has('more')
                    ->etc()));

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mobileNav.shortcuts.0.key', 'scheduled-visits')
                ->where('mobileNav.shortcuts.1.key', 'incidents')
                ->where('mobileNav.shortcuts.2.key', 'messages'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mobileNav.shortcuts.0.key', 'operations')
                ->where('mobileNav.shortcuts.1.key', 'incidents')
                ->where('mobileNav.shortcuts.2.key', 'messages'));
    }

    public function test_user_can_persist_authorized_mobile_shortcuts(): void
    {
        $user = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('mobile-nav-shortcuts.update'), [
                'shortcuts' => ['incidents', 'attendance', 'support-tickets'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            ['incidents', 'attendance', 'support-tickets'],
            $user->fresh()->mobile_nav_shortcuts,
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mobileNav.shortcuts.0.key', 'incidents')
                ->where('mobileNav.shortcuts.1.key', 'attendance')
                ->where('mobileNav.shortcuts.2.key', 'support-tickets'));
    }

    public function test_unauthorized_mobile_shortcuts_are_rejected(): void
    {
        $user = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->patch(route('mobile-nav-shortcuts.update'), [
                'shortcuts' => ['operations', 'incidents', 'messages'],
            ])
            ->assertSessionHasErrors('shortcuts')
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->mobile_nav_shortcuts);
    }

    public function test_role_or_permission_change_falls_back_from_inaccessible_stored_shortcuts(): void
    {
        $user = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $user->update([
            // Previously valid Admin-only destinations after a role change.
            'mobile_nav_shortcuts' => ['operations', 'documents', 'messages'],
        ]);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mobileNav.shortcuts.0.key', 'scheduled-visits')
                ->where('mobileNav.shortcuts.1.key', 'my-availability')
                ->where('mobileNav.shortcuts.2.key', 'messages'));
    }

    public function test_more_menu_destinations_match_authorized_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('mobileNav.more')
                ->where('mobileNav.more', function ($more): bool {
                    $items = collect($more);
                    $keys = $items->pluck('key')->all();

                    return in_array('operations', $keys, true)
                        && in_array('documents', $keys, true)
                        && in_array('dashboard', $keys, true)
                        && ! in_array('my-availability', $keys, true);
                }));
    }

    public function test_desktop_shell_markers_remain_present_with_mobile_nav_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('sidebarOpen')
                ->has('mobileNav.home')
                ->has('mobileNav.shortcuts', 3)
                ->has('mobileNav.more')
                ->has('mobileNav.available'));
    }

    public function test_guests_cannot_update_mobile_shortcuts(): void
    {
        $this->patch(route('mobile-nav-shortcuts.update'), [
            'shortcuts' => ['incidents', 'messages', 'attendance'],
        ])->assertRedirect(route('login'));
    }
}
