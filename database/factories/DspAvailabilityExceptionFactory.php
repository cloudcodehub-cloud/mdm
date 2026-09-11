<?php

namespace Database\Factories;

use App\Models\DspAvailabilityException;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DspAvailabilityException>
 */
class DspAvailabilityExceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'exception_date' => now()->toDateString(),
            'is_available' => false,
            'starts_at' => '16:00:00',
            'ends_at' => '23:59:00',
            'note' => 'Unavailable after 4 PM',
        ];
    }
}
