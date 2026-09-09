<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDspAssignment>
 */
class ClientDspAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'client_id' => Client::factory(),
            'status' => AssignmentStatus::Active,
            'started_on' => fake()->dateTimeBetween('-1 year', '-1 week')->format('Y-m-d'),
            'ended_on' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Inactive,
            'ended_on' => now()->toDateString(),
        ]);
    }

    public function forDsp(Employee $dsp): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $dsp->id,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
