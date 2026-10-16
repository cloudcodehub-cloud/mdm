<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\ClientContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientContactController extends Controller
{
    public function __construct(private ClientContactService $contacts) {}

    public function store(ClientContactRequest $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->contacts->create($client, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact saved.')]);

        return back();
    }

    public function update(
        ClientContactRequest $request,
        Client $client,
        ClientContact $contact,
    ): RedirectResponse {
        abort_unless($contact->client_id === $client->id, 404);
        $this->authorize('manageContacts', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->contacts->update($contact, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }

    public function destroy(
        Request $request,
        Client $client,
        ClientContact $contact,
    ): RedirectResponse {
        abort_unless($contact->client_id === $client->id, 404);
        $this->authorize('manageContacts', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->contacts->delete($contact, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed.')]);

        return back();
    }
}
