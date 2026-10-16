<?php

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'client_id' => Client::factory(),
            'reporter_employee_id' => Employee::factory()->dsp(),
            'responsible_supervisor_id' => null,
            'status' => IncidentStatus::Draft,
            'tracking_number' => null,
            'occurred_at' => now(),
            'original_category' => 'Injury / medical',
            'original_subcategory' => 'Fall',
            'official_category' => null,
            'official_subcategory' => null,
            'summary' => 'Client slipped near doorway.',
            'immediate_action' => 'Assisted client to chair and assessed for injury.',
            'injured' => false,
            'injury_type' => null,
            'injury_body_location' => null,
            'injury_medical_check' => null,
            'injury_notes' => null,
            'intervention' => false,
            'intervention_type' => null,
            'intervention_started_at' => null,
            'intervention_stopped_at' => null,
            'intervention_notes' => null,
            'supervisor_notified' => false,
            'additional_notes' => null,
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null,
            'location_method' => null,
            'location_status' => null,
            'submitted_at' => null,
            'acknowledged_at' => null,
            'acknowledged_by_user_id' => null,
            'acknowledgment_overdue_notified_at' => null,
            'discarded_at' => null,
            'discarded_by_user_id' => null,
        ];
    }

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (): array => [
            'visit_id' => $visit->id,
            'client_id' => $visit->client_id,
            'reporter_employee_id' => $visit->employee_id,
            'responsible_supervisor_id' => $visit->client?->supervisor_id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Submitted,
            'tracking_number' => 'INC-TEST-'.fake()->unique()->numerify('####'),
            'submitted_at' => now(),
        ]);
    }

    public function underReview(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => IncidentStatus::UnderReview,
            'acknowledged_at' => now(),
        ]);
    }
}
