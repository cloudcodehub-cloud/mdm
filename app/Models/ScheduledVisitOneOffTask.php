<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int|null $catalog_item_id
 * @property string $title
 * @property string|null $instructions
 * @property bool $note_required
 * @property bool $is_required
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 */
#[Fillable([
    'scheduled_visit_id',
    'catalog_item_id',
    'title',
    'instructions',
    'note_required',
    'is_required',
    'sort_order',
])]
class ScheduledVisitOneOffTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_required' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ScheduledVisit, $this>
     */
    public function scheduledVisit(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisit::class);
    }

    /**
     * @return HasMany<VisitTask, $this>
     */
    public function visitTasks(): HasMany
    {
        return $this->hasMany(VisitTask::class);
    }

    /**
     * @return BelongsTo<TaskCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(TaskCatalogItem::class, 'catalog_item_id');
    }
}
