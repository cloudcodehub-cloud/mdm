<?php

namespace Database\Factories;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Models\Client;
use App\Models\ClientAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientAuthorization>
 */
class ClientAuthorizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'authorization_number' => 'AUTH-'.fake()->unique()->numerify('######'),
            'payer' => fake()->randomElement(['Ohio Medicaid', 'County Board Waiver', 'Private Pay']),
            'service_type' => fake()->randomElement(['Residential Habilitation', 'Personal Care', 'Community Integration']),
            'starts_on' => fake()->dateTimeBetween('-8 months', '-1 month')->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween('+2 months', '+10 months')->format('Y-m-d'),
            'authorized_units' => fake()->randomElement(['20.00', '40.00', '80.00']),
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Active,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->addWeek()->toDateString(),
            'status' => AuthorizationStatus::Pending,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subMonth()->toDateString(),
            'status' => AuthorizationStatus::Expired,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
