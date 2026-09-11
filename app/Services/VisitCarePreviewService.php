<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\TaskCatalogItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VisitCarePreviewService
{
    public function __construct(private TaskRecurrenceMatcher $recurrence) {}

    /**
     * @param  list<int>  $serviceIds
     * @return array{tasks: list<array<string, mixed>>, catalog: list<array<string, mixed>>}
     */
    public function forClientOnDate(
        Client $client,
        string $serviceDate,
        array $serviceIds = [],
        ?ScheduledVisit $scheduledVisit = null,
    ): array {
        $day = Carbon::parse($serviceDate)->startOfDay();
        $plan = $client->carePlans()
            ->where('status', CarePlanStatus::Active)
            ->with([
                'taskTemplates' => fn ($query) => $query->active()->with('catalogItem')->orderBy('sort_order'),
            ])
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $day->toDateString());
            })
            ->orderByDesc('starts_on')
            ->first();

        $serviceMap = $this->serviceCatalogMap($serviceIds);
        $overrides = $this->overrideMap($scheduledVisit);
        $items = [];
        $seenCatalog = [];

        if ($plan !== null) {
            foreach ($plan->taskTemplates as $template) {
                $services = $this->servicesForCatalogItem($template->catalog_item_id, $serviceMap);

                if ($serviceIds !== [] && $template->catalog_item_id !== null && $services === []) {
                    continue;
                }

                $due = $this->recurrence->appliesOn($template, $day);
                $override = $overrides->get($template->id);
                $included = $override instanceof ScheduledVisitTaskOverride
                    ? $override->included
                    : $due;

                $items[] = $this->serializeTemplate($template, $due, $included, $services, $override);
                $seenCatalog[] = $template->catalog_item_id;
            }
        }

        $oneOffs = $scheduledVisit === null
            ? collect()
            : $scheduledVisit->oneOffTasks;

        foreach ($oneOffs as $task) {
            if ($task->catalog_item_id !== null) {
                $seenCatalog[] = $task->catalog_item_id;
            }
        }

        $seenIds = [];

        foreach ($seenCatalog as $id) {
            if (is_int($id) && $id > 0) {
                $seenIds[] = $id;
            }
        }

        return [
            'tasks' => $this->dedupeTasks($items),
            'catalog' => $this->catalogExtras($serviceMap, $seenIds),
        ];
    }

    /**
     * @param  list<int>  $serviceIds
     * @return Collection<int, array{id: int, name: string, catalog_item_ids: list<int>}>
     */
    private function serviceCatalogMap(array $serviceIds): Collection
    {
        if ($serviceIds === []) {
            return collect();
        }

        $services = CareService::query()
            ->with(['recommendedItems', 'recommendedBundles.items'])
            ->whereIn('id', $serviceIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $services->map(function (CareService $service): array {
            $ids = [];

            foreach ($service->recommendedItems as $item) {
                $ids[] = $item->id;
            }

            foreach ($service->recommendedBundles as $bundle) {
                foreach ($bundle->items as $item) {
                    $ids[] = $item->id;
                }
            }

            return [
                'id' => $service->id,
                'name' => $service->name,
                'catalog_item_ids' => array_values(array_unique($ids)),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array{id: int, name: string, catalog_item_ids: list<int>}>  $serviceMap
     * @return list<array{id: int, name: string}>
     */
    private function servicesForCatalogItem(?int $catalogItemId, Collection $serviceMap): array
    {
        if ($catalogItemId === null) {
            return [];
        }

        $matched = [];

        foreach ($serviceMap as $service) {
            if (in_array($catalogItemId, $service['catalog_item_ids'], true)) {
                $matched[] = [
                    'id' => $service['id'],
                    'name' => $service['name'],
                ];
            }
        }

        return $matched;
    }

    /**
     * @return Collection<int, ScheduledVisitTaskOverride>
     */
    private function overrideMap(?ScheduledVisit $scheduledVisit): Collection
    {
        if ($scheduledVisit === null) {
            return collect();
        }

        $scheduledVisit->loadMissing('taskOverrides');

        return $scheduledVisit->taskOverrides->keyBy('care_plan_task_template_id');
    }

    /**
     * @param  list<array{id: int, name: string}>  $services
     * @return array<string, mixed>
     */
    private function serializeTemplate(
        CarePlanTaskTemplate $template,
        bool $due,
        bool $included,
        array $services,
        ?ScheduledVisitTaskOverride $override,
    ): array {
        $group = $services[0]['name'] ?? 'Care plan';

        return [
            'key' => 'plan-'.$template->id,
            'care_plan_task_template_id' => $template->id,
            'catalog_item_id' => $template->catalog_item_id,
            'title' => $template->title,
            'instructions' => $template->instructions,
            'recurrence' => $template->recurrence->value,
            'recurrence_label' => $template->recurrence->label(),
            'preferred_timing' => $template->preferred_timing?->value,
            'preferred_timing_label' => $template->preferred_timing?->label(),
            'due' => $due,
            'due_label' => $due ? 'Due' : 'Not due',
            'included' => $included,
            'is_required' => $template->is_required,
            'is_critical' => $template->is_critical,
            'note_required' => $template->note_required,
            'source' => 'care_plan',
            'service_group' => $group,
            'services' => $services,
            'exclusion_reason' => $override?->exclusion_reason,
        ];
    }

    /**
     * @param  Collection<int, array{id: int, name: string, catalog_item_ids: list<int>}>  $serviceMap
     * @param  list<int>  $seenCatalog
     * @return list<array<string, mixed>>
     */
    private function catalogExtras(Collection $serviceMap, array $seenCatalog): array
    {
        $ids = $serviceMap->flatMap(fn (array $service) => $service['catalog_item_ids'])
            ->unique()
            ->reject(fn (int $id): bool => in_array($id, $seenCatalog, true))
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $extras = [];

        foreach (TaskCatalogItem::query()
            ->whereIn('id', $ids->all())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get() as $item) {
            $services = $this->servicesForCatalogItem($item->id, $serviceMap);
            $extras[] = [
                'id' => $item->id,
                'title' => $item->title,
                'instructions' => $item->instructions,
                'is_required' => $item->default_is_required,
                'note_required' => $item->default_note_required,
                'is_critical' => $item->default_is_critical,
                'service_group' => $services[0]['name'] ?? 'Catalog',
            ];
        }

        return $extras;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeTasks(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $identity = (string) ($item['care_plan_task_template_id'] ?? $item['one_off_id'] ?? $item['key']);

            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $unique[] = $item;
        }

        return $unique;
    }
}
