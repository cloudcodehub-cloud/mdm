<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function employee(Employee $employee, ProfilePhotoService $photos): StreamedResponse
    {
        $this->authorize('view', $employee);

        return $photos->streamEmployee($employee);
    }

    public function destroyEmployee(Employee $employee, ProfilePhotoService $photos): RedirectResponse
    {
        $this->authorize('update', $employee);
        $photos->removeEmployee($employee);

        return back();
    }

    public function client(Client $client, ProfilePhotoService $photos): StreamedResponse
    {
        $this->authorize('view', $client);

        return $photos->streamClient($client);
    }

    public function destroyClient(Client $client, ProfilePhotoService $photos): RedirectResponse
    {
        $this->authorize('update', $client);
        $photos->removeClient($client);

        return back();
    }
}
