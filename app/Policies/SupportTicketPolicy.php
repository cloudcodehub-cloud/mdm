<?php

namespace App\Policies;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessApplication();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        if (! $user->canAccessApplication()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $ticket->requester_user_id) {
            return true;
        }

        if ($user->hasItTechAccess()) {
            if ($ticket->status === SupportTicketStatus::Unassigned && $ticket->assigned_user_id === null) {
                return true; // summary queue visibility
            }

            return $ticket->assigned_user_id === $user->id;
        }

        return false;
    }

    public function viewFull(User $user, SupportTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        if ($user->isAdmin() || $user->id === $ticket->requester_user_id) {
            return true;
        }

        return $user->hasItTechAccess() && $ticket->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canAccessApplication();
    }

    public function claim(User $user, SupportTicket $ticket): bool
    {
        return $user->hasItTechAccess()
            && $ticket->status === SupportTicketStatus::Unassigned;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($user->id === $ticket->requester_user_id) {
            return in_array($ticket->status, [
                SupportTicketStatus::Unassigned,
                SupportTicketStatus::InProgress,
                SupportTicketStatus::Resolved,
            ], true);
        }

        return $user->hasItTechAccess()
            && $ticket->assigned_user_id === $user->id
            && in_array($ticket->status, [
                SupportTicketStatus::InProgress,
                SupportTicketStatus::Resolved,
            ], true);
    }

    public function resolve(User $user, SupportTicket $ticket): bool
    {
        return $user->hasItTechAccess()
            && $ticket->assigned_user_id === $user->id
            && $ticket->status === SupportTicketStatus::InProgress;
    }

    public function reopen(User $user, SupportTicket $ticket): bool
    {
        return $user->id === $ticket->requester_user_id
            && $ticket->status === SupportTicketStatus::Resolved
            && $ticket->isWithinReopenWindow();
    }

    public function cancel(User $user, SupportTicket $ticket): bool
    {
        return $user->id === $ticket->requester_user_id
            && $ticket->status->isActive();
    }

    public function reassign(User $user, SupportTicket $ticket): bool
    {
        return $user->hasItTechAccess()
            && $ticket->assigned_user_id === $user->id
            && in_array($ticket->status, [
                SupportTicketStatus::InProgress,
                SupportTicketStatus::Resolved,
            ], true);
    }

    public function takeover(User $user, SupportTicket $ticket): bool
    {
        return $user->hasItTechAccess()
            && $ticket->assigned_user_id !== null
            && $ticket->assigned_user_id !== $user->id
            && in_array($ticket->status, [
                SupportTicketStatus::InProgress,
                SupportTicketStatus::Resolved,
            ], true);
    }

    public function markDuplicate(User $user, SupportTicket $ticket): bool
    {
        if (! $user->hasItTechAccess()) {
            return false;
        }

        if ($ticket->status === SupportTicketStatus::Unassigned) {
            return true;
        }

        return $ticket->assigned_user_id === $user->id
            && in_array($ticket->status, [
                SupportTicketStatus::InProgress,
                SupportTicketStatus::Resolved,
            ], true);
    }

    public function downloadAttachment(User $user, SupportTicketAttachment $attachment): bool
    {
        $ticket = $attachment->ticket;

        return $this->viewFull($user, $ticket);
    }

    public function manageItTechAccess(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewMetrics(User $user): bool
    {
        return $user->isAdmin() || $user->hasItTechAccess();
    }
}
