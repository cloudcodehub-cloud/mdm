<?php

namespace App\Models;

use App\Enums\CarePlanStatus;
use App\Services\SettingsService;
use Database\Factories\CarePlanFactory;
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
 * @property int $client_id
 * @property int $revision_number
 * @property int|null $supersedes_care_plan_id
 * @property string $title
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $review_on
 * @property CarePlanStatus $status
 * @property string|null $notes
 * @property int|null $timed_check_interval_minutes
 * @property string|null $supervision_instruction
 * @property int|null $revised_by_user_id
 * @property string|null $revision_reason
 * @property Carbon|null $effective_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read CarePlan|null $supersedes
 * @property-read User|null $revisedBy
 * @property-read Collection<int, CarePlanTaskTemplate> $taskTemplates
 * @property-read Collection<int, Visit> $visits
 *
 * @method static Builder<static> currentlyActive()
 * @method static Builder<static> pending()
 */
#[Fillable([
    'client_id',
    'revision_number',
    'supersedes_care_plan_id',
    'title',
    'starts_on',
    'ends_on',
    'review_on',
    'status',
    'notes',
    'timed_check_interval_minutes',
    'supervision_instruction',
    'revised_by_user_id',
    'revision_reason',
    'effective_at',
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
            'review_on' => 'date',
            'status' => CarePlanStatus::class,
            'revision_number' => 'integer',
            'effective_at' => 'datetime',
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
     * @return BelongsTo<self, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_care_plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by_user_id');
    }

    /**
     * @return HasMany<CarePlanTaskTemplate, $this>
     */
    public function taskTemplates(): HasMany
    {
        return $this->hasMany(CarePlanTaskTemplate::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * @return HasMany<CarePlanEffectiveDateCorrection, $this>
     */
    public function effectiveDateCorrections(): HasMany
    {
        return $this->hasMany(CarePlanEffectiveDateCorrection::class)->orderByDesc('id');
    }

    public function isCurrentlyActive(?Carbon $on = null): bool
    {
        if ($this->status !== CarePlanStatus::Active) {
            return false;
        }

        $on = $on?->toDateString() ?? app(SettingsService::class)->today();
        $onDate = Carbon::parse($on)->startOfDay();

        if ($this->starts_on->gt($onDate)) {
            return false;
        }

        if ($this->ends_on !== null && $this->ends_on->lt($onDate)) {
            return false;
        }

        return true;
    }

    public function isPending(): bool
    {
        return $this->status === CarePlanStatus::Pending;
    }

    public function isHistorical(): bool
    {
        return $this->status === CarePlanStatus::Inactive;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = app(SettingsService::class)->today();

        return $query->where('status', CarePlanStatus::Active)
            ->whereDate('starts_on', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CarePlanStatus::Pending);
    }
}
