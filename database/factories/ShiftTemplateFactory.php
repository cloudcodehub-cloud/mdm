<?php

namespace Database\Factories;

use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '7–3',
            'code' => 'day_7_3_'.fake()->unique()->numerify('##'),
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'description' => 'Day shift, 7 a.m. to 3 p.m.',
            'is_active' => true,
        ];
    }

    public function day(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '7–3',
            'code' => 'day_7_3',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'description' => 'Day shift, 7 a.m. to 3 p.m.',
        ]);
    }

    public function evening(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '3–11',
            'code' => 'evening_3_11',
            'starts_at' => '15:00:00',
            'ends_at' => '23:00:00',
            'description' => 'Evening shift, 3 p.m. to 11 p.m.',
        ]);
    }

    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '11–7',
            'code' => 'overnight_11_7',
            'starts_at' => '23:00:00',
            'ends_at' => '07:00:00',
            'description' => 'Overnight shift, 11 p.m. to 7 a.m. next calendar day.',
        ]);
    }
}
