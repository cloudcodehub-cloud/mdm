<?php

namespace Tests\Feature\Domain;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Models\Client;
use App\Models\ClientAuthorization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_have_multiple_authorizations(): void
    {
        $client = Client::factory()->create();

        $active = ClientAuthorization::factory()->forClient($client)->create([
            'authorization_number' => 'AUTH-1001',
            'service_type' => 'Residential Habilitation',
        ]);

        $pending = ClientAuthorization::factory()->pending()->forClient($client)->create([
            'authorization_number' => 'AUTH-1002',
            'service_type' => 'Personal Care',
        ]);

        $this->assertCount(2, $client->authorizations);
        $this->assertTrue($active->isCurrentlyActive());
        $this->assertFalse($pending->isCurrentlyActive());
        $this->assertSame(AuthorizationUnit::Hour, $active->unit);
        $this->assertCount(1, ClientAuthorization::query()->currentlyActive()->get());
    }

    public function test_expired_authorizations_are_not_currently_active(): void
    {
        $expired = ClientAuthorization::factory()->expired()->create();

        $this->assertFalse($expired->isCurrentlyActive());
        $this->assertSame(AuthorizationStatus::Expired, $expired->status);
        $this->assertCount(0, ClientAuthorization::query()->currentlyActive()->get());
    }

    public function test_authorization_numbers_are_unique(): void
    {
        ClientAuthorization::factory()->create(['authorization_number' => 'AUTH-9001']);

        $this->expectException(QueryException::class);

        ClientAuthorization::factory()->create(['authorization_number' => 'AUTH-9001']);
    }
}
