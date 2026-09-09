<?php

namespace Tests\Feature\Domain;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_are_not_login_users(): void
    {
        $client = Client::factory()->create([
            'email' => 'elena.vasquez@clients.example.test',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'elena.vasquez@clients.example.test']);
    }

    public function test_clients_can_be_assigned_a_supervisor(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();

        $this->assertTrue($client->supervisor->is($supervisor));
        $this->assertTrue($supervisor->supervisedClients->contains($client));
    }

    public function test_client_lifecycle_uses_status_and_soft_deletes(): void
    {
        $client = Client::factory()->discharged()->create();

        $this->assertSame(ClientStatus::Discharged, $client->status);

        $client->delete();

        $this->assertSoftDeleted($client);
        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_client_numbers_are_unique(): void
    {
        Client::factory()->create(['client_number' => 'CLT-9001']);

        $this->expectException(QueryException::class);

        Client::factory()->create(['client_number' => 'CLT-9001']);
    }
}
