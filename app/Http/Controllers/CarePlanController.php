<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarePlanRequest;
use App\Http\Requests\SyncCarePlanTasksRequest;
use App\Models\CarePlan;
use App\Models\Client;
use App\Services\CarePlanRevisionService;
use App\Services\CarePlanSetupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CarePlanController extends Controller
{
    public function __construct(
        private CarePlanSetupService $setup,
        private CarePlanRevisionService $revisions,
    ) {}

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

        $data = $request->validated();
        $material = $this->hasMaterialCarePlanChange($carePlan, $data);

        if ($material) {
            $this->revisions->revise($carePlan, $data, $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Care plan revision saved.')]);
        } else {
            $this->revisions->applyClericalCorrection($carePlan, $data);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Care plan updated.')]);
        }

        return redirect()->route('clients.show', $carePlan->client_id);
    }

    public function syncTasks(SyncCarePlanTasksRequest $request, CarePlan $carePlan): RedirectResponse
    {
        $this->authorize('update', $carePlan);

        $validated = $request->validated();
        $plan = $this->revisions->syncTasksOrRevise(
            $carePlan,
            $validated['tasks'],
            [
                'effective_on' => $validated['effective_on'] ?? null,
                'revision_reason' => $validated['revision_reason'] ?? null,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $plan->id !== $carePlan->id
                ? __('Care plan revision saved.')
                : __('Care-plan tasks saved.'),
        ]);

        return redirect()->route('clients.show', $plan->client_id);
    }

    public function cancelPending(CarePlan $carePlan): RedirectResponse
    {
        $this->authorize('update', $carePlan);

        $this->revisions->cancelPending($carePlan, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pending Care Plan revision cancelled.')]);

        return redirect()->route('clients.show', $carePlan->client_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasMaterialCarePlanChange(CarePlan $carePlan, array $data): bool
    {
        if (isset($data['tasks']) && is_array($data['tasks'])) {
            return true;
        }

        foreach (['timed_check_interval_minutes', 'supervision_instruction', 'starts_on', 'ends_on', 'status'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $incoming = $data[$key];
            $current = $carePlan->{$key};

            if ($key === 'starts_on' || $key === 'ends_on') {
                $current = $current?->toDateString();
                $incoming = $incoming !== null ? (string) $incoming : null;
            } elseif ($key === 'status') {
                $current = $carePlan->status->value;
            }

            if ($incoming != $current) {
                return $carePlan->taskTemplates()->active()->exists()
                    || in_array($key, ['timed_check_interval_minutes', 'supervision_instruction'], true);
            }
        }

        return false;
    }
}
