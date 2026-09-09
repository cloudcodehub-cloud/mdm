<?php

namespace Tests\Feature\Domain;

use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DomainAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_employees_but_cannot_hard_delete_them(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Employee::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $employee));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Employee::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $employee));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $employee));
        $this->assertTrue(Gate::forUser($admin)->denies('forceDelete', $employee));
    }

    public function test_supervisors_are_scoped_to_their_assigned_employees_and_clients(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $report = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherReport = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();

        $user = $supervisor->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $report));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherReport));
        $this->assertTrue(Gate::forUser($user)->allows('view', $client));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherClient));
        $this->assertTrue(Gate::forUser($user)->denies('create', Employee::class));
    }

    public function test_dsps_can_view_their_own_profile_and_assigned_clients(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $otherDsp = Employee::factory()->dsp()->create();
        $assignedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        ClientDspAssignment::factory()->forDsp($dsp)->forClient($assignedClient)->create();

        $user = $dsp->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('view', $dsp));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherDsp));
        $this->assertTrue(Gate::forUser($user)->allows('view', $assignedClient));
        $this->assertTrue(Gate::forUser($user)->denies('view', $otherClient));
        $this->assertTrue(Gate::forUser($user)->denies('viewAny', Employee::class));
    }
}
