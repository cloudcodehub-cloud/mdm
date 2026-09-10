<?php

namespace Database\Factories;

use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledVisit>
 */
class ScheduledVisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'employee_id' => Employee::factory()->dsp(),
            'supervisor_id' => null,
            'shift_template_id' => null,
            'service_date' => now()->toDateString(),
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => null,
        ];
    }

    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => '23:00:00',
            'ends_at' => '07:00:00',
            'shift_template_id' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduledVisitStatus::Cancelled,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }

    public function forDsp(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
