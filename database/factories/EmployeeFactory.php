<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('####'),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.4)->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('555-###-####'),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-21 years')->format('Y-m-d'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.2)->bothify('Apt ##'),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['OH', 'PA', 'IN', 'MI', 'KY']),
            'postal_code' => fake()->postcode(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend']),
            'emergency_contact_phone' => fake()->numerify('555-###-####'),
            'hired_on' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'terminated_on' => null,
            'employment_status' => EmploymentStatus::Active,
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
            'supervisor_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function withUser(?Role $role = Role::Dsp): static
    {
        return $this->afterCreating(function (Employee $employee) use ($role): void {
            $user = User::factory()->create([
                'name' => $employee->full_name,
                'email' => $employee->email ?? fake()->unique()->safeEmail(),
                'role' => $role,
            ]);

            $employee->update(['user_id' => $user->id]);
        });
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_title' => 'Supervisor',
            'job_type' => JobType::Supervisor,
        ])->withUser(Role::Supervisor);
    }

    public function dsp(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
        ])->withUser(Role::Dsp);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Inactive,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Terminated,
            'terminated_on' => now()->toDateString(),
        ]);
    }

    public function forSupervisor(Employee $supervisor): static
    {
        return $this->state(fn (array $attributes) => [
            'supervisor_id' => $supervisor->id,
        ]);
    }
}
