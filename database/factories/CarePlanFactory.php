<?php

namespace Database\Factories;

use App\Enums\CarePlanStatus;
use App\Models\CarePlan;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarePlan>
 */
class CarePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => 'Individual Service Plan',
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(10)->toDateString(),
            'status' => CarePlanStatus::Active,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => now()->subYears(2)->toDateString(),
            'ends_on' => now()->subMonths(3)->toDateString(),
            'status' => CarePlanStatus::Inactive,
            'title' => 'Prior Individual Service Plan',
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
