<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int $care_plan_task_template_id
 * @property bool $included
 * @property string|null $exclusion_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read CarePlanTaskTemplate $template
 */
#[Fillable([
    'scheduled_visit_id',
    'care_plan_task_template_id',
    'included',
    'exclusion_reason',
])]
class ScheduledVisitTaskOverride extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'included' => 'boolean',
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
     * @return BelongsTo<CarePlanTaskTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CarePlanTaskTemplate::class, 'care_plan_task_template_id');
    }
}
