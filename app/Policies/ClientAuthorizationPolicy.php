<?php

namespace App\Policies;

use App\Models\ClientAuthorization;
use App\Models\User;

class ClientAuthorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, ClientAuthorization $clientAuthorization): bool
    {
        if ($user->isDsp()) {
            return false;
        }

        return $user->can('view', $clientAuthorization->client);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClientAuthorization $clientAuthorization): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ClientAuthorization $clientAuthorization): bool
    {
        return false;
    }

    public function restore(User $user, ClientAuthorization $clientAuthorization): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ClientAuthorization $clientAuthorization): bool
    {
        return false;
    }
}
