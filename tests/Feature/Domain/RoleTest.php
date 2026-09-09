<?php

namespace Tests\Feature\Domain;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_default_to_the_dsp_role(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->isDsp());
        $this->assertSame(Role::Dsp, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isSupervisor());
    }

    public function test_admin_supervisor_and_dsp_roles_are_distinct(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = User::factory()->supervisor()->create();
        $dsp = User::factory()->dsp()->create();

        $this->assertTrue($admin->hasRole(Role::Admin));
        $this->assertTrue($supervisor->hasRole(Role::Supervisor));
        $this->assertTrue($dsp->hasRole(Role::Dsp));

        $this->assertFalse($admin->isSupervisor());
        $this->assertFalse($supervisor->isDsp());
        $this->assertFalse($dsp->isAdmin());
    }

    public function test_new_registrations_receive_the_dsp_role(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'Riley Demo',
            'email' => 'riley.demo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertTrue(User::query()->where('email', 'riley.demo@example.com')->firstOrFail()->isDsp());
    }
}
