<?php

namespace Database\Factories;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Models\Visit;
use App\Models\VisitException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitException>
 */
class VisitExceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'visit_task_id' => null,
            'type' => VisitExceptionType::OtherVisitException,
            'status' => VisitExceptionStatus::Open,
            'message' => 'Visit exception recorded for supervisor review.',
            'context' => null,
        ];
    }
}
