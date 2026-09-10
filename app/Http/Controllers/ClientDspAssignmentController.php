<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateClientDspAssignmentRequest;
use App\Http\Requests\StoreClientDspAssignmentRequest;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Services\ClientAssignmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClientDspAssignmentController extends Controller
{
    public function store(StoreClientDspAssignmentRequest $request, Client $client, ClientAssignmentService $assignments): RedirectResponse
    {
        $this->authorize('view', $client);

        $assignments->assign($client, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('DSP assigned to client.')]);

        return redirect()->route('clients.show', $client);
    }

    public function deactivate(DeactivateClientDspAssignmentRequest $request, ClientDspAssignment $assignment, ClientAssignmentService $assignments): RedirectResponse
    {
        $assignments->deactivate($assignment, $request->validated('ended_on'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deactivated.')]);

        return redirect()->route('clients.show', $assignment->client);
    }
}
