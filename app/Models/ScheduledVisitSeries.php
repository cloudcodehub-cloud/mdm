<?php

namespace App\Models;

use App\Enums\VisitRecurrencePattern;
use Database\Factories\ScheduledVisitSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int $employee_id
 * @property int|null $supervisor_id
 * @property int|null $shift_template_id
 * @property string $service_type
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string|null $notes
 * @property VisitRecurrencePattern $pattern
 * @property int $interval
 * @property list<int>|null $days_of_week
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $occurrence_count
 * @property int|null $created_by_user_id
 *
 * @property-read Client $client
 * @property-read Employee $employee
 * @property-read Collection<int, ScheduledVisit> $visits
 */
#[Fillable([
    'client_id',
    'employee_id',
    'supervisor_id',
    'shift_template_id',
    'service_type',
    'starts_at',
    'ends_at',
    'notes',
    'pattern',
    'interval',
    'days_of_week',
    'starts_on',
    'ends_on',
    'occurrence_count',
    'created_by_user_id',
])]
class ScheduledVisitSeries extends Model
{
    /** @use HasFactory<ScheduledVisitSeriesFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pattern' => VisitRecurrencePattern::class,
            'interval' => 'integer',
            'days_of_week' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'occurrence_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * @return BelongsTo<ShiftTemplate, $this>
     */
    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ScheduledVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class, 'series_id');
    }
}
