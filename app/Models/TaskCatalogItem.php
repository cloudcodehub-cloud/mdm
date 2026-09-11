<?php

namespace App\Models;

use App\Enums\TaskCatalogCategory;
use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property TaskCatalogCategory $category
 * @property string $title
 * @property string|null $instructions
 * @property TaskRecurrence $default_recurrence
 * @property string|null $default_recurrence_detail
 * @property list<int>|null $default_weekdays
 * @property int|null $default_interval_weeks
 * @property TaskPreferredTiming|null $default_preferred_timing
 * @property bool $default_is_required
 * @property bool $default_note_required
 * @property bool $default_can_skip
 * @property bool $default_is_critical
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TaskCatalogBundle> $bundles
 */
#[Fillable([
    'slug',
    'category',
    'title',
    'instructions',
    'default_recurrence',
    'default_recurrence_detail',
    'default_weekdays',
    'default_interval_weeks',
    'default_preferred_timing',
    'default_is_required',
    'default_note_required',
    'default_can_skip',
    'default_is_critical',
    'sort_order',
    'is_active',
])]
class TaskCatalogItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TaskCatalogCategory::class,
            'default_recurrence' => TaskRecurrence::class,
            'default_weekdays' => 'array',
            'default_interval_weeks' => 'integer',
            'default_preferred_timing' => TaskPreferredTiming::class,
            'default_is_required' => 'boolean',
            'default_note_required' => 'boolean',
            'default_can_skip' => 'boolean',
            'default_is_critical' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<TaskCatalogBundle, $this>
     */
    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(TaskCatalogBundle::class, 'task_catalog_bundle_items')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return HasMany<CarePlanTaskTemplate, $this>
     */
    public function carePlanTemplates(): HasMany
    {
        return $this->hasMany(CarePlanTaskTemplate::class, 'catalog_item_id');
    }
}
