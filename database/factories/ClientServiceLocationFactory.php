<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientServiceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientServiceLocation>
 */
class ClientServiceLocationFactory extends Factory
{
    protected $model = ClientServiceLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'label' => 'Primary',
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(39, 42),
            'longitude' => fake()->longitude(-84, -80),
            'is_primary' => true,
            'is_active' => true,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => ['client_id' => $client->id]);
    }

    public function alternate(string $label = 'Day program'): static
    {
        return $this->state(fn (): array => [
            'label' => $label,
            'is_primary' => false,
        ]);
    }
}
