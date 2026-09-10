<?php

namespace Database\Factories;

use App\Models\SkipReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkipReason>
 */
class SkipReasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Client refused',
            'code' => 'client_refused_'.fake()->unique()->numerify('##'),
            'requires_comment' => false,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Other',
            'code' => 'other',
            'requires_comment' => true,
            'sort_order' => 7,
        ]);
    }
}
