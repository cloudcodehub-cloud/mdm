<?php

namespace Database\Factories;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_visit_id' => ScheduledVisit::factory(),
            'employee_id' => Employee::factory()->dsp(),
            'client_id' => Client::factory(),
            'service_type' => 'Personal Care',
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now(),
            'clock_in_latitude' => null,
            'clock_in_longitude' => null,
            'clock_in_accuracy' => null,
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
            'clock_in_unavailable_reason' => 'GPS unavailable in test.',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Visit $visit): void {
            $scheduled = ScheduledVisit::query()->find($visit->scheduled_visit_id);

            if (! $scheduled instanceof ScheduledVisit) {
                return;
            }

            $visit->employee_id = $scheduled->employee_id;
            $visit->client_id = $scheduled->client_id;
            $visit->service_type = $scheduled->service_type;
        });
    }

    public function withGps(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in_latitude' => '40.1234567',
            'clock_in_longitude' => '-82.9876543',
            'clock_in_accuracy' => '12.50',
            'clock_in_location_method' => ClockInLocationMethod::BrowserGps,
            'clock_in_location_status' => ClockInLocationStatus::Captured,
            'clock_in_unavailable_reason' => null,
        ]);
    }

    public function forScheduledVisit(ScheduledVisit $scheduledVisit): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_visit_id' => $scheduledVisit->id,
            'employee_id' => $scheduledVisit->employee_id,
            'client_id' => $scheduledVisit->client_id,
            'service_type' => $scheduledVisit->service_type,
        ]);
    }
}
