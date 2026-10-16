<?php

namespace Database\Factories;

use App\Enums\ClientContactRole;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContact>
 */
class ClientContactFactory extends Factory
{
    protected $model = ClientContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'role' => ClientContactRole::Emergency,
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Parent', 'Sibling', 'Spouse', 'Guardian']),
            'phone' => fake()->numerify('555-###-####'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => null,
            'is_primary' => true,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => ['client_id' => $client->id]);
    }

    public function guardian(): static
    {
        return $this->state(fn (): array => [
            'role' => ClientContactRole::Guardian,
            'is_primary' => false,
        ]);
    }
}
