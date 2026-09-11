<?php

namespace App\Models;

use App\Enums\PreferredDaypart;
use Database\Factories\DspWeeklyAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $weekday
 * @property bool $is_available
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property PreferredDaypart|null $preferred_daypart
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'weekday',
    'is_available',
    'starts_at',
    'ends_at',
    'preferred_daypart',
])]
class DspWeeklyAvailability extends Model
{
    /** @use HasFactory<DspWeeklyAvailabilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_available' => 'boolean',
            'preferred_daypart' => PreferredDaypart::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
