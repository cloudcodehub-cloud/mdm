<?php

namespace App\Http\Controllers;

use App\Http\Requests\CareServiceRequest;
use App\Models\CareService;
use App\Support\CareServicePresenter;
use App\Support\TaskCatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CareServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CareService::class);

        $user = $request->user();
        abort_unless($user !== null, 401);

        return Inertia::render('care-services/index', [
            'services' => CareServicePresenter::catalog($user->isAdmin()),
            'task_catalog' => TaskCatalogPresenter::payload(),
            'can' => [
                'manage' => $user->can('create', CareService::class),
            ],
        ]);
    }

    public function store(CareServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $service = CareService::query()->create([
            'slug' => CareService::uniqueSlug($data['name']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'note_required' => $request->boolean('note_required'),
            'supervisor_review_expected' => $request->boolean('supervisor_review_expected'),
            'payer_code' => $data['payer_code'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? ((int) CareService::query()->max('sort_order') + 1)),
        ]);

        $this->syncRecommendations($service, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service saved.')]);

        return redirect()->route('care-services.index');
    }

    public function update(CareServiceRequest $request, CareService $careService): RedirectResponse
    {
        $data = $request->validated();
        $careService->update([
            'slug' => CareService::uniqueSlug($data['name'], $careService->id),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', $careService->is_active),
            'note_required' => $request->boolean('note_required'),
            'supervisor_review_expected' => $request->boolean('supervisor_review_expected'),
            'payer_code' => $data['payer_code'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $careService->sort_order),
        ]);

        $this->syncRecommendations($careService, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return redirect()->route('care-services.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRecommendations(CareService $service, array $data): void
    {
        $bundles = [];
        $order = 0;

        foreach ($data['recommended_bundle_ids'] ?? [] as $bundleId) {
            if (is_numeric($bundleId)) {
                $order++;
                $bundles[(int) $bundleId] = ['sort_order' => $order];
            }
        }

        $items = [];
        $itemOrder = 0;

        foreach ($data['recommended_item_ids'] ?? [] as $itemId) {
            if (is_numeric($itemId)) {
                $itemOrder++;
                $items[(int) $itemId] = ['sort_order' => $itemOrder];
            }
        }

        $service->recommendedBundles()->sync($bundles);
        $service->recommendedItems()->sync($items);
    }
}
