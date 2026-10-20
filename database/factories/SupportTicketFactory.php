<?php

namespace Database\Factories;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketWorkImpact;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        $workImpact = fake()->randomElement(SupportTicketWorkImpact::cases());

        return [
            'ticket_number' => sprintf('IT-%06d', fake()->unique()->numberBetween(1, 999999)),
            'requester_user_id' => User::factory(),
            'assigned_user_id' => null,
            'status' => SupportTicketStatus::Unassigned,
            'category' => fake()->randomElement(SupportTicketCategory::cases()),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'work_impact' => $workImpact,
            'urgency' => $workImpact->urgency(),
            'resolution_note' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'duplicate_of_ticket_id' => null,
        ];
    }

    public function forRequester(User $user): static
    {
        return $this->state(fn (): array => [
            'requester_user_id' => $user->id,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => [
            'assigned_user_id' => $user->id,
            'status' => SupportTicketStatus::InProgress,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Resolved,
            'resolution_note' => 'Issue addressed.',
            'resolved_at' => now(),
        ]);
    }
}
