<?php

namespace Tests\Feature\Domain;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_the_expected_foundation_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('role', Role::Admin)->count());
        $this->assertSame(2, User::query()->where('role', Role::Supervisor)->count());
        $this->assertSame(5, User::query()->where('role', Role::Dsp)->count());

        $this->assertSame(7, Employee::query()->count());
        $this->assertSame(6, Client::query()->count());
        $this->assertSame(7, ClientDspAssignment::query()->count());
        $this->assertSame(6, ClientDspAssignment::query()->active()->count());

        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $this->assertNull($admin->employee);
        $this->assertTrue($admin->isAdmin());

        $this->assertNotNull(Employee::query()->where('employee_number', 'EMP-1001')->first()?->user);
        $this->assertTrue(
            User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail()
                ->employee
                ->assignedClients
                ->contains(fn (Client $client): bool => $client->client_number === 'CLT-3001'),
        );
    }

    public function test_demo_accounts_use_the_documented_password(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(auth()->attempt([
            'email' => 'admin@mdm.test',
            'password' => DemoSeeder::PASSWORD,
        ]));
    }

    public function test_employee_and_client_factories_create_valid_records(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $assignment = ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $this->assertDatabaseHas('employees', ['id' => $dsp->id, 'supervisor_id' => $supervisor->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'supervisor_id' => $supervisor->id]);
        $this->assertDatabaseHas('client_dsp_assignments', [
            'id' => $assignment->id,
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
        ]);
    }
}
