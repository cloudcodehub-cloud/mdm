<?php

namespace App\Models;

use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use Database\Factories\CarePlanTaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $care_plan_id
 * @property int|null $catalog_item_id
 * @property string $title
 * @property string|null $instructions
 * @property TaskRecurrence $recurrence
 * @property string|null $recurrence_detail
 * @property list<int>|null $weekdays
 * @property int|null $interval_weeks
 * @property TaskPreferredTiming|null $preferred_timing
 * @property bool $is_required
 * @property bool $note_required
 * @property bool $can_skip
 * @property bool $is_critical
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CarePlan $carePlan
 * @property-read TaskCatalogItem|null $catalogItem
 * @property-read Collection<int, VisitTask> $visitTasks
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'care_plan_id',
    'catalog_item_id',
    'title',
    'instructions',
    'recurrence',
    'recurrence_detail',
    'weekdays',
    'interval_weeks',
    'preferred_timing',
    'is_required',
    'note_required',
    'can_skip',
    'is_critical',
    'sort_order',
    'is_active',
])]
class CarePlanTaskTemplate extends Model
{
    /** @use HasFactory<CarePlanTaskTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrence' => TaskRecurrence::class,
            'weekdays' => 'array',
            'interval_weeks' => 'integer',
            'preferred_timing' => TaskPreferredTiming::class,
            'is_required' => 'boolean',
            'note_required' => 'boolean',
            'can_skip' => 'boolean',
            'is_critical' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CarePlan, $this>
     */
    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    /**
     * @return BelongsTo<TaskCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(TaskCatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return HasMany<VisitTask, $this>
     */
    public function visitTasks(): HasMany
    {
        return $this->hasMany(VisitTask::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
