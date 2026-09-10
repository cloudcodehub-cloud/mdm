<?php

namespace App\Models;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\VisitStatus;
use Database\Factories\VisitFactory;
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
 * @property int $scheduled_visit_id
 * @property int $employee_id
 * @property int $client_id
 * @property string $service_type
 * @property VisitStatus $status
 * @property Carbon $clocked_in_at
 * @property Carbon|null $clocked_out_at
 * @property string|null $clock_in_latitude
 * @property string|null $clock_in_longitude
 * @property string|null $clock_in_accuracy
 * @property ClockInLocationMethod $clock_in_location_method
 * @property ClockInLocationStatus $clock_in_location_status
 * @property string|null $clock_in_unavailable_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read Employee $employee
 * @property-read Client $client
 * @property-read Collection<int, VisitTask> $tasks
 *
 * @method static Builder<static> inProgress()
 * @method static Builder<static> visibleTo(User $user)
 */
#[Fillable([
    'scheduled_visit_id',
    'employee_id',
    'client_id',
    'service_type',
    'status',
    'clocked_in_at',
    'clocked_out_at',
    'clock_in_latitude',
    'clock_in_longitude',
    'clock_in_accuracy',
    'clock_in_location_method',
    'clock_in_location_status',
    'clock_in_unavailable_reason',
])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
            'clock_in_location_method' => ClockInLocationMethod::class,
            'clock_in_location_status' => ClockInLocationStatus::class,
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<VisitTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(VisitTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', VisitStatus::InProgress);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $employee = $user->employee;

        if ($employee === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSupervisor()) {
            return $query->whereIn(
                'scheduled_visit_id',
                ScheduledVisit::query()->visibleTo($user)->select('id'),
            );
        }

        if ($user->isDsp()) {
            return $query->where('employee_id', $employee->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
