<?php

namespace Database\Factories;

use App\Enums\InAppNotificationType;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InAppNotification>
 */
class InAppNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => InAppNotificationType::Message,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'url' => '/messages',
            'source_key' => 'message:'.fake()->unique()->numerify('####'),
            'read_at' => null,
        ];
    }
}
