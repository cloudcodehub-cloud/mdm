<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Services\SettingsService;
use Database\Factories\ClientAuthorizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $authorization_number
 * @property string $payer
 * @property string $service_type
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $authorized_units
 * @property AuthorizationUnit $unit
 * @property AuthorizationStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 *
 * @method static Builder<static> currentlyActive()
 */
#[Fillable([
    'client_id',
    'authorization_number',
    'payer',
    'service_type',
    'starts_on',
    'ends_on',
    'authorized_units',
    'unit',
    'status',
    'notes',
])]
class ClientAuthorization extends Model
{
    /** @use HasFactory<ClientAuthorizationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'authorized_units' => 'decimal:2',
            'unit' => AuthorizationUnit::class,
            'status' => AuthorizationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isCurrentlyActive(?Carbon $on = null): bool
    {
        if ($this->status !== AuthorizationStatus::Active) {
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = app(SettingsService::class)->today();

        return $query->where('status', AuthorizationStatus::Active)
            ->whereDate('starts_on', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            });
    }
}
