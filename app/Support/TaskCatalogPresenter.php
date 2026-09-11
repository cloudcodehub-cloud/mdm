<?php

namespace App\Support;

use App\Enums\TaskCatalogCategory;
use App\Models\TaskCatalogBundle;
use App\Models\TaskCatalogItem;
use Illuminate\Support\Collection;

class TaskCatalogPresenter
{
    /**
     * @return array{categories: list<array{value: string, label: string}>, items: list<array<string, mixed>>, bundles: list<array<string, mixed>>}
     */
    public static function payload(): array
    {
        $items = TaskCatalogItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $bundles = TaskCatalogBundle::query()
            ->with(['items' => fn ($query) => $query->where('task_catalog_items.is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return [
            'categories' => array_map(
                fn (TaskCatalogCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ],
                TaskCatalogCategory::cases(),
            ),
            'items' => self::items($items),
            'bundles' => self::values($bundles->map(fn (TaskCatalogBundle $bundle): array => [
                'id' => $bundle->id,
                'slug' => $bundle->slug,
                'name' => $bundle->name,
                'description' => $bundle->description,
                'item_ids' => $bundle->items->pluck('id')->values()->all(),
            ])),
        ];
    }

    /**
     * @param  Collection<int, TaskCatalogItem>  $items
     * @return list<array<string, mixed>>
     */
    public static function items(Collection $items): array
    {
        return self::values($items->map(fn (TaskCatalogItem $item): array => [
            'id' => $item->id,
            'slug' => $item->slug,
            'category' => $item->category->value,
            'category_label' => $item->category->label(),
            'title' => $item->title,
            'instructions' => $item->instructions,
            'default_recurrence' => $item->default_recurrence->value,
            'default_recurrence_label' => $item->default_recurrence->label(),
            'default_recurrence_detail' => $item->default_recurrence_detail,
            'default_weekdays' => $item->default_weekdays,
            'default_interval_weeks' => $item->default_interval_weeks,
            'default_preferred_timing' => $item->default_preferred_timing?->value,
            'default_preferred_timing_label' => $item->default_preferred_timing?->label(),
            'default_is_required' => $item->default_is_required,
            'default_note_required' => $item->default_note_required,
            'default_can_skip' => $item->default_can_skip,
            'default_is_critical' => $item->default_is_critical,
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
