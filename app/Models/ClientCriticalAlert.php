<?php

namespace App\Models;

use App\Enums\CriticalAlertCategory;
use Database\Factories\ClientCriticalAlertFactory;
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
 * @property CriticalAlertCategory $category
 * @property string $title
 * @property string $body
 * @property int $version
 * @property bool $is_urgent
 * @property bool $is_active
 * @property int $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read User $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $deactivatedBy
 * @property-read Collection<int, ClientCriticalAlertAcknowledgment> $acknowledgments
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'client_id',
    'category',
    'title',
    'body',
    'version',
    'is_urgent',
    'is_active',
    'created_by_user_id',
    'updated_by_user_id',
    'deactivated_at',
    'deactivated_by_user_id',
])]
class ClientCriticalAlert extends Model
{
    /** @use HasFactory<ClientCriticalAlertFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CriticalAlertCategory::class,
            'version' => 'integer',
            'is_urgent' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_user_id');
    }

    /**
     * @return HasMany<ClientCriticalAlertAcknowledgment, $this>
     */
    public function acknowledgments(): HasMany
    {
        return $this->hasMany(ClientCriticalAlertAcknowledgment::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
