<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_critical_alert_id
 * @property int $alert_version
 * @property int $visit_id
 * @property int $user_id
 * @property Carbon $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClientCriticalAlert $alert
 * @property-read Visit $visit
 * @property-read User $user
 */
#[Fillable([
    'client_critical_alert_id',
    'alert_version',
    'visit_id',
    'user_id',
    'acknowledged_at',
])]
class ClientCriticalAlertAcknowledgment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_version' => 'integer',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClientCriticalAlert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(ClientCriticalAlert::class, 'client_critical_alert_id');
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
