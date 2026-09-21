<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DemoEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoToolsSharedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_testing_environment_exposes_demo_helper_state(): void
    {
        $this->assertTrue(DemoEnvironment::toolsEnabled());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('demoTools.enabled', true));
    }

    public function test_production_environment_does_not_expose_demo_helper_state(): void
    {
        $this->app['env'] = 'production';
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertFalse(DemoEnvironment::toolsEnabled());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('demoTools.enabled', false));
    }

    public function test_guest_pages_do_not_need_demo_tools_for_login(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
