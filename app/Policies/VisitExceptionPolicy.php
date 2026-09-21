<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitException;

class VisitExceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, VisitException $exception): bool
    {
        if (! $user->isAdmin() && ! $user->isSupervisor()) {
            return false;
        }

        $exception->loadMissing('visit.scheduledVisit');

        return $user->can('view', $exception->visit);
    }

    public function review(User $user, VisitException $exception): bool
    {
        return $this->manage($user, $exception) && $exception->isOpen();
    }

    public function resolve(User $user, VisitException $exception): bool
    {
        return $this->manage($user, $exception) && ! $exception->isResolved();
    }

    public function followUp(User $user, VisitException $exception): bool
    {
        return $this->manage($user, $exception) && ! $exception->isResolved();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VisitException $exception): bool
    {
        return false;
    }

    public function delete(User $user, VisitException $exception): bool
    {
        return false;
    }

    public function restore(User $user, VisitException $exception): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, VisitException $exception): bool
    {
        return false;
    }

    private function manage(User $user, VisitException $exception): bool
    {
        if (! $user->isAdmin() && ! $user->isSupervisor()) {
            return false;
        }

        return $this->view($user, $exception);
    }
}
