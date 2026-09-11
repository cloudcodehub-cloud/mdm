<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarePlanRequest;
use App\Http\Requests\SyncCarePlanTasksRequest;
use App\Models\CarePlan;
use App\Models\Client;
use App\Services\CarePlanSetupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CarePlanController extends Controller
{
    public function __construct(private CarePlanSetupService $setup) {}

    public function store(CarePlanRequest $request, Client $client): RedirectResponse
    {
        abort_unless((int) $request->validated('client_id') === $client->id, 404);
        $this->authorize('manageCarePlan', $client);

        $data = $request->validated();
        $plan = $this->setup->createPlan($client, $data);

        if (isset($data['tasks']) && is_array($data['tasks'])) {
            $tasks = [];

            foreach ($data['tasks'] as $task) {
                if (is_array($task)) {
                    $tasks[] = $task;
                }
            }

            $this->setup->syncTasks($plan, $tasks);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Care plan created.')]);

        return redirect()->route('clients.show', $client);
    }

    public function update(CarePlanRequest $request, CarePlan $carePlan): RedirectResponse
    {
        $this->authorize('update', $carePlan);

        $carePlan->update($request->safe()->except('client_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Care plan updated.')]);

        return redirect()->route('clients.show', $carePlan->client_id);
    }

    public function syncTasks(SyncCarePlanTasksRequest $request, CarePlan $carePlan): RedirectResponse
    {
        $this->setup->syncTasks($carePlan, $request->validated('tasks'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Care-plan tasks saved.')]);

        return redirect()->route('clients.show', $carePlan->client_id);
    }
}
