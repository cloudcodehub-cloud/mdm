<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'participant_key' => fake()->unique()->uuid(),
        ];
    }

    public function between(User $first, User $second): static
    {
        return $this->state(fn (array $attributes) => [
            'participant_key' => Conversation::participantKeyFor($first, $second),
        ])->afterCreating(function (Conversation $conversation) use ($first, $second): void {
            $conversation->participants()->syncWithoutDetaching([$first->id, $second->id]);
        });
    }
}
