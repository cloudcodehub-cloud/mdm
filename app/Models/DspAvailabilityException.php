<?php

namespace App\Models;

use Database\Factories\DspAvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $exception_date
 * @property bool $is_available
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string|null $note
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'exception_date',
    'is_available',
    'starts_at',
    'ends_at',
    'note',
])]
class DspAvailabilityException extends Model
{
    /** @use HasFactory<DspAvailabilityExceptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'is_available' => 'boolean',
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
