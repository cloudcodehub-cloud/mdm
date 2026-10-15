<?php

namespace App\Http\Controllers;

use App\Http\Requests\CriticalAlertRequest;
use App\Models\Client;
use App\Models\ClientCriticalAlert;
use App\Models\Visit;
use App\Services\CriticalAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CriticalAlertController extends Controller
{
    public function __construct(private CriticalAlertService $alerts) {}

    public function store(CriticalAlertRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('manageCriticalAlerts', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->alerts->create($client, $user, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Critical alert created.'),
        ]);

        return back();
    }

    public function update(
        CriticalAlertRequest $request,
        Client $client,
        ClientCriticalAlert $criticalAlert,
    ): RedirectResponse {
        abort_unless($criticalAlert->client_id === $client->id, 404);
        $this->authorize('manageCriticalAlerts', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->alerts->update($criticalAlert, $user, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Critical alert updated.'),
        ]);

        return back();
    }

    public function deactivate(
        Request $request,
        Client $client,
        ClientCriticalAlert $criticalAlert,
    ): RedirectResponse {
        abort_unless($criticalAlert->client_id === $client->id, 404);
        $this->authorize('manageCriticalAlerts', $client);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->alerts->deactivate($criticalAlert, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Critical alert deactivated.'),
        ]);

        return back();
    }

    public function acknowledge(
        Request $request,
        Visit $visit,
        ClientCriticalAlert $criticalAlert,
    ): RedirectResponse {
        $this->authorize('acknowledgeCriticalAlert', $visit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->alerts->acknowledge($visit, $criticalAlert, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Critical alert acknowledged.'),
        ]);

        return redirect()->route('visits.show', $visit);
    }
}
