<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Employee;
use App\Models\EmployeeProfileChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfileChangeRequest>
 */
class EmployeeProfileChangeRequestFactory extends Factory
{
    protected $model = EmployeeProfileChangeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory()->dsp()->create();

        return [
            'employee_id' => $employee->id,
            'status' => ReviewStatus::Pending,
            'proposed_values' => [
                'cell_phone' => '555-0100',
                'city' => 'Columbus',
            ],
            'current_values' => [
                'cell_phone' => $employee->cell_phone,
                'city' => $employee->city,
            ],
            'reason' => 'Updated contact details',
            'requested_by_user_id' => $employee->user_id,
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
