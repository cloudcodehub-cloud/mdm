<?php

namespace App\Models;

use App\Enums\TaskRecurrence;
use Database\Factories\CarePlanTaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $care_plan_id
 * @property string $title
 * @property string|null $instructions
 * @property TaskRecurrence $recurrence
 * @property string|null $recurrence_detail
 * @property bool $is_required
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CarePlan $carePlan
 */
#[Fillable([
    'care_plan_id',
    'title',
    'instructions',
    'recurrence',
    'recurrence_detail',
    'is_required',
    'sort_order',
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
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CarePlan, $this>
     */
    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }
}
