<?php

namespace Tests\Feature\Domain;

use App\Enums\AssignmentStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDspAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dsp_can_be_assigned_to_multiple_clients(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();

        ClientDspAssignment::factory()->forDsp($dsp)->forClient($firstClient)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($secondClient)->create();

        $this->assertCount(2, $dsp->assignedClients);
        $this->assertTrue($dsp->assignedClients->contains($firstClient));
        $this->assertTrue($dsp->assignedClients->contains($secondClient));
    }

    public function test_a_client_can_be_assigned_to_multiple_dsps(): void
    {
        $client = Client::factory()->create();
        $firstDsp = Employee::factory()->dsp()->create();
        $secondDsp = Employee::factory()->dsp()->create();

        ClientDspAssignment::factory()->forDsp($firstDsp)->forClient($client)->create();
        ClientDspAssignment::factory()->forDsp($secondDsp)->forClient($client)->create();

        $this->assertCount(2, $client->assignedDsps);
        $this->assertTrue($client->assignedDsps->contains($firstDsp));
        $this->assertTrue($client->assignedDsps->contains($secondDsp));
    }

    public function test_assignments_support_active_and_historical_records(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $active = ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create([
            'started_on' => '2024-06-01',
        ]);

        $historical = ClientDspAssignment::factory()->inactive()->forDsp($dsp)->forClient($client)->create([
            'started_on' => '2023-01-01',
            'ended_on' => '2023-12-31',
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($historical->isActive());
        $this->assertSame(AssignmentStatus::Inactive, $historical->status);
        $this->assertCount(1, ClientDspAssignment::query()->active()->get());
        $this->assertTrue($active->dsp->is($dsp));
        $this->assertTrue($historical->client->is($client));
    }
}
