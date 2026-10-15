<?php

namespace Database\Factories;

use App\Enums\CriticalAlertCategory;
use App\Models\Client;
use App\Models\ClientCriticalAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCriticalAlert>
 */
class ClientCriticalAlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'category' => CriticalAlertCategory::FallRisk,
            'title' => 'Fall risk — use gait belt',
            'body' => 'Client requires gait-belt assistance for all transfers.',
            'version' => 1,
            'is_urgent' => false,
            'is_active' => true,
            'created_by_user_id' => User::factory()->admin(),
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_urgent' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
