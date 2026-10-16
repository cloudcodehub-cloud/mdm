<?php

namespace App\Models;

use App\Enums\ClientAdminHistoryCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property ClientAdminHistoryCategory $category
 * @property string $summary
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property Carbon|null $effective_on
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'client_id',
    'category',
    'summary',
    'old_value',
    'new_value',
    'reason',
    'effective_on',
    'actor_user_id',
    'subject_type',
    'subject_id',
    'meta',
])]
class ClientAdminHistoryEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ClientAdminHistoryCategory::class,
            'effective_on' => 'date',
            'meta' => 'array',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
