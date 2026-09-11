<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTimeOff>
 */
class EmployeeTimeOffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'starts_at' => null,
            'ends_at' => null,
            'reason' => 'Personal day',
            'status' => ReviewStatus::Approved,
            'requested_by_user_id' => User::factory()->dsp(),
            'submitted_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Pending,
        ]);
    }
}
