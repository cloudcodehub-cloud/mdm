<?php

namespace Tests\Feature;

use App\Enums\JobType;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupervisorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_open_supervisor_directory_and_caseload(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $jordan = Employee::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('supervisors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supervisors/index')
                ->has('supervisors.data')
                ->where('supervisors.data.0.job_type', JobType::Supervisor->value)
            );

        $this->actingAs($admin)
            ->get(route('supervisors.show', $jordan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supervisors/show')
                ->where('supervisor.id', $jordan->id)
                ->where('operations.metrics.0.key', 'assigned_dsps')
                ->where('operations.metrics.0.value', 3)
                ->where('operations.metrics.1.value', 4)
            );
    }

    public function test_supervisor_and_dsp_cannot_open_supervisor_directory(): void
    {
        $this->seed(DemoSeeder::class);
        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $jordan = Employee::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($supervisor)->get(route('supervisors.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('supervisors.show', $jordan))->assertForbidden();
        $this->actingAs($dsp)->get(route('supervisors.index'))->assertForbidden();
    }

    public function test_supervisor_directory_does_not_include_dsps(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->supervisor()->create(['first_name' => 'Pat', 'last_name' => 'Supervisor']);
        Employee::factory()->dsp()->create(['first_name' => 'Sam', 'last_name' => 'Dsp']);

        $this->actingAs($admin)
            ->get(route('supervisors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('supervisors.data', 1)
                ->where('supervisors.data.0.name', fn (string $name): bool => str_contains($name, 'Pat'))
            );
    }

    public function test_non_supervisor_employee_detail_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->get(route('supervisors.show', $dsp))
            ->assertNotFound();
    }
}
