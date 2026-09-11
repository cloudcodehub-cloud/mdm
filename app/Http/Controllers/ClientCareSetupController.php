<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientCareSetupRequest;
use App\Models\CarePlan;
use App\Models\Client;
use App\Services\CarePlanSetupService;
use App\Support\CareServicePresenter;
use App\Support\DirectoryPresenter;
use App\Support\TaskCatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientCareSetupController extends Controller
{
    public function edit(Client $client): Response
    {
        $this->authorize('manageCarePlan', $client);

        $client->load(['careServices', 'carePlans.taskTemplates', 'supervisor']);
        $active = $client->carePlans()->currentlyActive()->with('taskTemplates')->orderByDesc('starts_on')->first();

        return Inertia::render('clients/setup', [
            'client' => DirectoryPresenter::clientDetail($client),
            'services' => CareServicePresenter::catalog(),
            'selected_service_ids' => $client->careServices->pluck('id')->values()->all(),
            'task_catalog' => TaskCatalogPresenter::payload(),
            'selected_tasks' => $this->selectedTasks($active),
        ]);
    }

    public function update(ClientCareSetupRequest $request, Client $client, CarePlanSetupService $setup): RedirectResponse
    {
        $setup->completeSetup($client, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Care plan saved.')]);

        return redirect()->route('clients.show', $client);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectedTasks(?CarePlan $active): array
    {
        if ($active === null) {
            return [];
        }

        $plans = DirectoryPresenter::carePlans(collect([$active]));
        $first = $plans[0] ?? null;

        if (! is_array($first) || ! isset($first['tasks']) || ! is_array($first['tasks'])) {
            return [];
        }

        $tasks = [];

        foreach ($first['tasks'] as $task) {
            if (is_array($task)) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }
}
