<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visit;
use App\Models\VisitSafetyInstruction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitSafetyInstruction>
 */
class VisitSafetyInstructionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'content' => 'Remain within arm’s reach during all transfers for the rest of this visit.',
            'is_urgent' => true,
            'created_by_user_id' => User::factory()->admin(),
            'effective_at' => now(),
        ];
    }

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'visit_id' => $visit->id,
        ]);
    }

    public function acknowledged(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
            'acknowledged_by_user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
