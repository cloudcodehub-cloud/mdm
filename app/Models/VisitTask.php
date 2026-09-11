<?php

namespace App\Models;

use App\Enums\TaskPreferredTiming;
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
 * @property int|null $care_plan_task_template_id
 * @property int|null $scheduled_visit_one_off_task_id
 * @property string $title
 * @property string|null $instructions
 * @property TaskRecurrence $recurrence
 * @property string|null $recurrence_detail
 * @property bool $is_required
 * @property bool $note_required
 * @property bool $can_skip
 * @property bool $is_critical
 * @property TaskPreferredTiming|null $preferred_timing
 * @property int $sort_order
 * @property VisitTaskStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $skipped_at
 * @property int|null $skip_reason_id
 * @property string|null $skip_comment
 * @property string|null $completion_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read CarePlanTaskTemplate|null $carePlanTaskTemplate
 * @property-read ScheduledVisitOneOffTask|null $oneOffTask
 * @property-read SkipReason|null $skipReason
 */
#[Fillable([
    'visit_id',
    'care_plan_task_template_id',
    'scheduled_visit_one_off_task_id',
    'title',
    'instructions',
    'recurrence',
    'recurrence_detail',
    'is_required',
    'note_required',
    'can_skip',
    'is_critical',
    'preferred_timing',
    'sort_order',
    'status',
    'completed_at',
    'skipped_at',
    'skip_reason_id',
    'skip_comment',
    'completion_note',
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
            'preferred_timing' => TaskPreferredTiming::class,
            'is_required' => 'boolean',
            'note_required' => 'boolean',
            'can_skip' => 'boolean',
            'is_critical' => 'boolean',
            'sort_order' => 'integer',
            'status' => VisitTaskStatus::class,
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
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

    /**
     * @return BelongsTo<ScheduledVisitOneOffTask, $this>
     */
    public function oneOffTask(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisitOneOffTask::class, 'scheduled_visit_one_off_task_id');
    }

    /**
     * @return BelongsTo<SkipReason, $this>
     */
    public function skipReason(): BelongsTo
    {
        return $this->belongsTo(SkipReason::class);
    }
}
