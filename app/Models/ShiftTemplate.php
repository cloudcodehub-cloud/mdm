<?php

namespace App\Models;

use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Database\Factories\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ScheduledVisit> $scheduledVisits
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'name',
    'code',
    'starts_at',
    'ends_at',
    'description',
    'is_active',
])]
class ShiftTemplate extends Model
{
    /** @use HasFactory<ShiftTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * True when the shift ends on the next calendar day (for example 11–7).
     */
    public function spansOvernight(): bool
    {
        return $this->normalizedTime($this->ends_at) <= $this->normalizedTime($this->starts_at);
    }

    public function durationInMinutes(): int
    {
        $reference = Carbon::parse('2000-01-01')->startOfDay();

        return (int) $this->startsAtOn($reference)->diffInMinutes($this->endsAtOn($reference));
    }

    public function startsAtOn(CarbonInterface $serviceDate): CarbonInterface
    {
        return $this->clockOn($serviceDate, $this->starts_at);
    }

    public function endsAtOn(CarbonInterface $serviceDate): CarbonInterface
    {
        $end = $this->clockOn($serviceDate, $this->ends_at);

        if ($this->spansOvernight()) {
            return $end->addDay();
        }

        return $end;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<ScheduledVisit, $this>
     */
    public function scheduledVisits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class);
    }

    private function clockOn(CarbonInterface $serviceDate, string $time): CarbonInterface
    {
        return app(SettingsService::class)->at($serviceDate->toDateString(), $time);
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
