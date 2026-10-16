<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientServiceLocationRequest;
use App\Models\Client;
use App\Models\ClientServiceLocation;
use App\Services\ClientServiceLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientServiceLocationController extends Controller
{
    public function __construct(private ClientServiceLocationService $locations) {}

    public function store(ClientServiceLocationRequest $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->locations->create($client, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service location saved.')]);

        return back();
    }

    public function update(
        ClientServiceLocationRequest $request,
        Client $client,
        ClientServiceLocation $service_location,
    ): RedirectResponse {
        abort_unless($service_location->client_id === $client->id, 404);
        $this->authorize('manageServiceLocations', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->locations->update($service_location, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service location updated.')]);

        return back();
    }

    public function deactivate(
        Request $request,
        Client $client,
        ClientServiceLocation $service_location,
    ): RedirectResponse {
        abort_unless($service_location->client_id === $client->id, 404);
        $this->authorize('manageServiceLocations', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->locations->deactivate($service_location, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service location deactivated.')]);

        return back();
    }
}
