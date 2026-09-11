<?php

namespace App\Support;

use App\Models\CareService;
use Illuminate\Support\Collection;

class CareServicePresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function catalog(bool $includeInactive = false): array
    {
        $query = CareService::query()
            ->with(['recommendedBundles', 'recommendedItems'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! $includeInactive) {
            $query->active();
        }

        return self::values($query->get()->map(fn (CareService $service): array => self::detail($service)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(CareService $service): array
    {
        $service->loadMissing(['recommendedBundles', 'recommendedItems']);

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'description' => $service->description,
            'is_active' => $service->is_active,
            'note_required' => $service->note_required,
            'supervisor_review_expected' => $service->supervisor_review_expected,
            'payer_code' => $service->payer_code,
            'sort_order' => $service->sort_order,
            'recommended_bundle_ids' => $service->recommendedBundles->pluck('id')->values()->all(),
            'recommended_item_ids' => $service->recommendedItems->pluck('id')->values()->all(),
            'recommended_bundle_names' => $service->recommendedBundles->pluck('name')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, CareService>  $services
     * @return list<array{id: int, name: string, slug: string}>
     */
    public static function options(Collection $services): array
    {
        return self::values($services->map(fn (CareService $service): array => [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
        ]));
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private static function values(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }
}
