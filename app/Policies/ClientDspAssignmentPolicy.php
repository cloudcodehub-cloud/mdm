<?php

namespace App\Policies;

use App\Models\ClientDspAssignment;
use App\Models\User;

class ClientDspAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, ClientDspAssignment $clientDspAssignment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp() && $user->employee) {
            return $clientDspAssignment->employee_id === $user->employee->id;
        }

        if ($user->isSupervisor() && $user->employee) {
            $dsp = $clientDspAssignment->employee;
            $client = $clientDspAssignment->client;

            return $dsp->supervisor_id === $user->employee->id
                || $client->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClientDspAssignment $clientDspAssignment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ClientDspAssignment $clientDspAssignment): bool
    {
        return false;
    }

    public function restore(User $user, ClientDspAssignment $clientDspAssignment): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ClientDspAssignment $clientDspAssignment): bool
    {
        return false;
    }
}
