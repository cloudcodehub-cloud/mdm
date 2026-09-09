<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_number' => 'CLT-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->numerify('555-###-####'),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.2)->bothify('Apt ##'),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['OH', 'PA', 'IN', 'MI', 'KY']),
            'postal_code' => fake()->postcode(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => fake()->randomElement(['Parent', 'Sibling', 'Guardian', 'Spouse']),
            'emergency_contact_phone' => fake()->numerify('555-###-####'),
            'status' => ClientStatus::Active,
            'supervisor_id' => null,
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Inactive,
        ]);
    }

    public function discharged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Discharged,
        ]);
    }

    public function forSupervisor(Employee $supervisor): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_id' => $supervisor->id,
        ]);
    }
}
