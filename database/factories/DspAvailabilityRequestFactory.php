<?php

namespace Database\Factories;

use App\Enums\AvailabilityRequestType;
use App\Enums\ReviewStatus;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DspAvailabilityRequest>
 */
class DspAvailabilityRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'requested_by_user_id' => User::factory()->dsp(),
            'type' => AvailabilityRequestType::Weekly,
            'effective_on' => now()->toDateString(),
            'payload' => [
                'days' => [
                    [
                        'weekday' => 1,
                        'is_available' => true,
                        'starts_at' => '08:00:00',
                        'ends_at' => '16:00:00',
                    ],
                ],
            ],
            'reason' => 'School pickup change',
            'status' => ReviewStatus::Pending,
            'submitted_at' => now(),
        ];
    }
}
