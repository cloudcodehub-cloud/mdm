<?php

namespace App\Models;

use App\Enums\IncidentHistoryAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $incident_id
 * @property IncidentHistoryAction $action
 * @property string $summary
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Incident $incident
 * @property-read User|null $actor
 */
#[Fillable([
    'incident_id',
    'action',
    'summary',
    'old_value',
    'new_value',
    'reason',
    'actor_user_id',
    'meta',
])]
class IncidentHistoryEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => IncidentHistoryAction::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
