<?php

namespace App\Models;

use Database\Factories\ClientServiceLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property string $label
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_primary
 * @property bool $is_active
 */
#[Fillable([
    'client_id',
    'label',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'latitude',
    'longitude',
    'is_primary',
    'is_active',
])]
class ClientServiceLocation extends Model
{
    /** @use HasFactory<ClientServiceLocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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
     * @return HasMany<ScheduledVisit, $this>
     */
    public function scheduledVisits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayLabel(): string
    {
        $address = collect([
            $this->address_line_1,
            $this->address_line_2,
            trim(implode(', ', array_filter([$this->city, $this->state]))),
            $this->postal_code,
        ])->filter(fn (?string $part): bool => filled($part))->implode(', ');

        return filled($address) ? $this->label.' — '.$address : $this->label;
    }
}
