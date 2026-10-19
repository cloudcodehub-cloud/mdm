<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientAuthorizationRequest;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Services\ClientAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClientAuthorizationController extends Controller
{
    public function __construct(private ClientAuthorizationService $authorizations) {}

    public function store(ClientAuthorizationRequest $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('create', ClientAuthorization::class);
        abort_unless((int) $request->validated('client_id') === $client->id, 404);

        $result = $this->authorizations->create($client, $user, $request->validated());

        $message = __('Authorization saved.');
        if ($result['active_visit_warning'] !== null) {
            $message .= ' '.$result['active_visit_warning'];
        }

        Inertia::flash('toast', [
            'type' => $result['active_visit_warning'] !== null ? 'warning' : 'success',
            'message' => $message,
        ]);

        return back();
    }

    public function update(
        ClientAuthorizationRequest $request,
        Client $client,
        ClientAuthorization $authorization,
    ): RedirectResponse {
        abort_unless($authorization->client_id === $client->id, 404);
        $this->authorize('update', $authorization);

        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless((int) $request->validated('client_id') === $client->id, 404);

        $result = $this->authorizations->update($authorization, $user, $request->validated());

        $message = __('Authorization updated.');
        if ($result['active_visit_warning'] !== null) {
            $message .= ' '.$result['active_visit_warning'];
        }

        Inertia::flash('toast', [
            'type' => $result['active_visit_warning'] !== null ? 'warning' : 'success',
            'message' => $message,
        ]);

        return back();
    }
}
