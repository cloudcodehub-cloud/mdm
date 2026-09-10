<?php

namespace Database\Factories;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCredential>
 */
class EmployeeCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = CredentialType::cases();
        $type = $types[array_rand($types)];

        return [
            'employee_id' => Employee::factory()->dsp(),
            'type' => $type,
            'name' => match ($type) {
                CredentialType::Cpr => 'CPR Certification',
                CredentialType::FirstAid => 'First Aid Certification',
                CredentialType::DriversLicense => "Driver's License",
                CredentialType::BackgroundCheck => 'Background Check',
                CredentialType::MedicationAdministration => 'Medication Administration',
                CredentialType::TbScreening => 'TB Screening',
            },
            'issuer' => fake()->randomElement(['American Red Cross', 'Ohio BMV', 'County Board', 'State Health Dept']),
            'credential_number' => fake()->optional(0.7)->bothify('CRD-####-####'),
            'issued_on' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'expires_on' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'status' => CredentialStatus::Active,
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_on' => now()->subYears(3)->toDateString(),
            'expires_on' => now()->subMonth()->toDateString(),
            'status' => CredentialStatus::Expired,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_on' => null,
            'expires_on' => null,
            'status' => CredentialStatus::Pending,
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
