<?php

namespace App\Models;

use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use Carbon\CarbonInterface;
use Database\Factories\ScheduledVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property int $client_id
 * @property int $employee_id
 * @property int|null $supervisor_id
 * @property int|null $shift_template_id
 * @property Carbon $service_date
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string $service_type
 * @property ScheduledVisitStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read Employee $employee
 * @property-read Employee $dsp
 * @property-read Employee|null $supervisor
 * @property-read ShiftTemplate|null $shiftTemplate
 *
 * @method static Builder<static> scheduled()
 * @method static Builder<static> visibleTo(User $user)
 */
#[Fillable([
    'client_id',
    'employee_id',
    'supervisor_id',
    'shift_template_id',
    'service_date',
    'starts_at',
    'ends_at',
    'service_type',
    'status',
    'notes',
])]
class ScheduledVisit extends Model
{
    /** @use HasFactory<ScheduledVisitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'status' => ScheduledVisitStatus::class,
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
     * Assigned DSP employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * DSP alias for the assigned employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function dsp(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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

    public function usesShiftTemplate(): bool
    {
        return $this->shift_template_id !== null;
    }

    public function spansOvernight(): bool
    {
        if ($this->starts_at !== null && $this->ends_at !== null) {
            return $this->normalizedTime($this->ends_at) <= $this->normalizedTime($this->starts_at);
        }

        return $this->shiftTemplate?->spansOvernight() ?? false;
    }

    public function startsAtOn(?CarbonInterface $serviceDate = null): CarbonInterface
    {
        $date = $serviceDate ?? $this->service_date;

        if ($this->starts_at !== null) {
            return $this->clockOn($date, $this->starts_at);
        }

        if ($this->shiftTemplate !== null) {
            return $this->shiftTemplate->startsAtOn($date);
        }

        throw new RuntimeException('Scheduled visit is missing both explicit start time and a shift template.');
    }

    public function endsAtOn(?CarbonInterface $serviceDate = null): CarbonInterface
    {
        $date = $serviceDate ?? $this->service_date;

        if ($this->ends_at !== null) {
            $end = $this->clockOn($date, $this->ends_at);

            if ($this->spansOvernight()) {
                return $end->addDay();
            }

            return $end;
        }

        if ($this->shiftTemplate !== null) {
            return $this->shiftTemplate->endsAtOn($date);
        }

        throw new RuntimeException('Scheduled visit is missing both explicit end time and a shift template.');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', ScheduledVisitStatus::Scheduled);
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
            $dspIds = Employee::query()
                ->where('supervisor_id', $employee->id)
                ->where('job_type', JobType::Dsp)
                ->pluck('id');
            $clientIds = Client::query()
                ->where('supervisor_id', $employee->id)
                ->pluck('id');

            return $query->where(function (Builder $builder) use ($employee, $dspIds, $clientIds): void {
                $builder->where('supervisor_id', $employee->id)
                    ->orWhereIn('employee_id', $dspIds)
                    ->orWhereIn('client_id', $clientIds);
            });
        }

        if ($user->isDsp()) {
            return $query->where('employee_id', $employee->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function clockOn(CarbonInterface $serviceDate, string $time): CarbonInterface
    {
        [$hour, $minute, $second] = $this->timeParts($time);

        return $serviceDate->toImmutable()->setTime($hour, $minute, $second);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function timeParts(string $time): array
    {
        $normalized = $this->normalizedTime($time);
        $parts = explode(':', $normalized);

        return [
            (int) $parts[0],
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    private function normalizedTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return substr($time, 0, 8);
    }
}
