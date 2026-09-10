<?php

namespace App\Models;

use App\Enums\CarePlanStatus;
use Database\Factories\CarePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $title
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property CarePlanStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CarePlanTaskTemplate> $taskTemplates
 *
 * @method static Builder<static> currentlyActive()
 */
#[Fillable([
    'client_id',
    'title',
    'starts_on',
    'ends_on',
    'status',
    'notes',
])]
class CarePlan extends Model
{
    /** @use HasFactory<CarePlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => CarePlanStatus::class,
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
     * @return HasMany<CarePlanTaskTemplate, $this>
     */
    public function taskTemplates(): HasMany
    {
        return $this->hasMany(CarePlanTaskTemplate::class)->orderBy('sort_order');
    }

    public function isCurrentlyActive(?Carbon $on = null): bool
    {
        if ($this->status !== CarePlanStatus::Active) {
            return false;
        }

        $on = ($on ?? now())->startOfDay();

        if ($this->starts_on->gt($on)) {
            return false;
        }

        if ($this->ends_on !== null && $this->ends_on->lt($on)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', CarePlanStatus::Active)
            ->whereDate('starts_on', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            });
    }
}
