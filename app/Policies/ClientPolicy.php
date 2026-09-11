<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            return $client->supervisor_id === $user->employee->id;
        }

        if ($user->isDsp() && $user->employee) {
            return $user->employee->clientAssignments()
                ->active()
                ->where('client_id', $client->id)
                ->exists();
        }

        return false;
    }

    public function manageCarePlan(User $user, Client $client): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return $this->view($user, $client);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Client $client): bool
    {
        return false;
    }

    public function restore(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return false;
    }
}
