<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use Database\Factories\EmployeeTrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $title
 * @property string|null $provider
 * @property Carbon|null $completed_on
 * @property Carbon|null $expires_on
 * @property string|null $hours
 * @property TrainingStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 *
 * @method static Builder<static> currentlyValid()
 */
#[Fillable([
    'employee_id',
    'title',
    'provider',
    'completed_on',
    'expires_on',
    'hours',
    'status',
    'notes',
])]
class EmployeeTraining extends Model
{
    /** @use HasFactory<EmployeeTrainingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'expires_on' => 'date',
            'hours' => 'decimal:2',
            'status' => TrainingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isExpired(?Carbon $on = null): bool
    {
        if ($this->expires_on === null) {
            return $this->status === TrainingStatus::Expired;
        }

        return $this->expires_on->lt(($on ?? now())->startOfDay());
    }

    public function isCurrentlyValid(?Carbon $on = null): bool
    {
        if ($this->status !== TrainingStatus::Completed) {
            return false;
        }

        return ! $this->isExpired($on);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where('status', TrainingStatus::Completed)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_on')
                    ->orWhereDate('expires_on', '>=', now()->toDateString());
            });
    }
}
