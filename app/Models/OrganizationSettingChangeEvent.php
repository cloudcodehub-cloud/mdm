<?php

namespace App\Models;

use App\Enums\OrganizationSettingChangeKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property OrganizationSettingChangeKey|string $setting_key
 * @property string $summary
 * @property string|null $previous_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property Carbon|null $effective_at
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $actor
 */
#[Fillable([
    'setting_key',
    'summary',
    'previous_value',
    'new_value',
    'reason',
    'effective_at',
    'actor_user_id',
    'meta',
])]
class OrganizationSettingChangeEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'setting_key' => OrganizationSettingChangeKey::class,
            'effective_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
