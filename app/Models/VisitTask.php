<?php

namespace App\Models;

use App\Enums\TaskRecurrence;
use App\Enums\VisitTaskStatus;
use Database\Factories\VisitTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $care_plan_task_template_id
 * @property string $title
 * @property string|null $instructions
 * @property TaskRecurrence $recurrence
 * @property string|null $recurrence_detail
 * @property bool $is_required
 * @property int $sort_order
 * @property VisitTaskStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read CarePlanTaskTemplate $carePlanTaskTemplate
 */
#[Fillable([
    'visit_id',
    'care_plan_task_template_id',
    'title',
    'instructions',
    'recurrence',
    'recurrence_detail',
    'is_required',
    'sort_order',
    'status',
])]
class VisitTask extends Model
{
    /** @use HasFactory<VisitTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrence' => TaskRecurrence::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'status' => VisitTaskStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<CarePlanTaskTemplate, $this>
     */
    public function carePlanTaskTemplate(): BelongsTo
    {
        return $this->belongsTo(CarePlanTaskTemplate::class);
    }
}
