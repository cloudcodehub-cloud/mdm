<?php

namespace App\Services;

use App\Enums\SupportTicketHistoryAction;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 12 Decisions 12-01, 12-31, 12-32 — Admin grants/removes restricted IT/Tech access.
 */
class ItTechAccessService
{
    public function grant(User $actor, User $target): User
    {
        $this->assertAdmin($actor);

        if ($target->hasItTechAccess()) {
            return $target;
        }

        $target->forceFill(['it_tech_access' => true])->save();

        return $target->refresh();
    }

    public function revoke(User $actor, User $target): User
    {
        $this->assertAdmin($actor);

        if (! $target->hasItTechAccess()) {
            return $target;
        }

        DB::transaction(function () use ($actor, $target): void {
            $target->forceFill(['it_tech_access' => false])->save();

            SupportTicket::query()
                ->where('assigned_user_id', $target->id)
                ->whereIn('status', [
                    SupportTicketStatus::Unassigned->value,
                    SupportTicketStatus::InProgress->value,
                    SupportTicketStatus::Resolved->value,
                ])
                ->orderBy('id')
                ->each(function (SupportTicket $ticket) use ($actor): void {
                    $ticket->forceFill([
                        'assigned_user_id' => null,
                        'status' => SupportTicketStatus::Unassigned,
                        'resolution_note' => null,
                        'resolved_at' => null,
                    ])->save();

                    SupportTicketHistoryEvent::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'actor_user_id' => $actor->id,
                        'action' => SupportTicketHistoryAction::ReturnedToUnassigned,
                        'payload' => [
                            'reason' => 'it_tech_access_revoked',
                        ],
                    ]);
                });
        });

        return $target->refresh();
    }

    private function assertAdmin(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'it_tech_access' => 'Only Admin may grant or remove IT/Tech access.',
            ]);
        }
    }
}
