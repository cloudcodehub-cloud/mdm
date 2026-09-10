<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_filter_clients(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('clients.index', ['search' => 'Elena', 'status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/index')
                ->where('can.create', true)
                ->has('clients.data', 1)
            );
    }

    public function test_admin_can_create_edit_and_inactivate_a_client(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'first_name' => 'Jordan',
                'last_name' => 'Cole',
                'status' => ClientStatus::Active->value,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertRedirect();

        $client = Client::query()->where('last_name', 'Cole')->firstOrFail();
        $this->assertNotNull($client->client_number);
        $this->assertTrue($client->supervisor?->is($supervisor));

        $this->actingAs($admin)
            ->put(route('clients.update', $client), [
                'first_name' => 'Jordan',
                'last_name' => 'Cole-Updated',
                'status' => ClientStatus::Active->value,
                'supervisor_id' => $supervisor->id,
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Inactive->value,
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('Cole-Updated', $client->fresh()?->last_name);
        $this->assertSame(ClientStatus::Inactive, $client->fresh()?->status);
    }

    public function test_admin_can_assign_and_deactivate_a_dsp_without_deleting_history(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->post(route('clients.assignments.store', $client), [
                'employee_id' => $dsp->id,
                'started_on' => '2026-09-01',
                'notes' => 'Primary coverage',
            ])
            ->assertRedirect(route('clients.show', $client));

        $assignment = ClientDspAssignment::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertTrue($assignment->isActive());

        $this->actingAs($admin)
            ->patch(route('assignments.deactivate', $assignment), [
                'ended_on' => '2026-09-10',
            ])
            ->assertRedirect(route('clients.show', $client));

        $assignment->refresh();
        $this->assertFalse($assignment->isActive());
        $this->assertSame(AssignmentStatus::Inactive, $assignment->status);
        $this->assertSame('2026-09-10', $assignment->ended_on?->toDateString());
        $this->assertDatabaseHas('client_dsp_assignments', ['id' => $assignment->id]);
    }

    public function test_client_destroy_route_is_not_defined(): void
    {
        $this->assertFalse(Route::has('clients.destroy'));
    }

    public function test_supervisor_is_scoped_to_assigned_clients(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $assigned = Client::factory()->forSupervisor($supervisor)->create();
        $other = Client::factory()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/index')
                ->where('can.create', false)
                ->has('clients.data', 1)
                ->where('clients.data.0.id', $assigned->id)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('clients.show', $other))
            ->assertForbidden();
    }

    public function test_dsp_cannot_open_client_management(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.index'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.create'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('clients.store'), [
                'first_name' => 'Blocked',
                'last_name' => 'Client',
                'status' => ClientStatus::Active->value,
            ])
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertForbidden();
    }

    public function test_admin_client_detail_includes_related_records(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $client = Client::query()->where('first_name', 'Elena')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('client.id', $client->id)
                ->has('authorizations')
                ->has('carePlans')
                ->has('assignments')
                ->has('scheduledVisits')
                ->where('can.manageAssignments', true)
            );
    }
}
