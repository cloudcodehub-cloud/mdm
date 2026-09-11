<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'author_id' => User::factory()->admin(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => AnnouncementAudience::Everyone,
            'published_at' => now(),
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
