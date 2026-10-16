<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $care_plan_id
 * @property Carbon $previous_starts_on
 * @property Carbon|null $previous_effective_at
 * @property Carbon $corrected_starts_on
 * @property Carbon|null $corrected_effective_at
 * @property string $reason
 * @property int $corrected_by_user_id
 */
#[Fillable([
    'care_plan_id',
    'previous_starts_on',
    'previous_effective_at',
    'corrected_starts_on',
    'corrected_effective_at',
    'reason',
    'corrected_by_user_id',
])]
class CarePlanEffectiveDateCorrection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_starts_on' => 'date',
            'previous_effective_at' => 'datetime',
            'corrected_starts_on' => 'date',
            'corrected_effective_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }
}
