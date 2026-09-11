<?php

namespace Database\Factories;

use App\Models\CareService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CareService>
 */
class CareServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Service '.fake()->unique()->numerify('####');

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'note_required' => false,
            'supervisor_review_expected' => false,
            'payer_code' => null,
            'sort_order' => 1,
        ];
    }
}
