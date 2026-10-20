<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property string $from_outcome
 * @property string $to_outcome
 * @property string $reason
 * @property string|null $evidence_note
 * @property int $reclassified_by_user_id
 * @property Carbon $reclassified_at
 */
class VisitOutcomeReclassification extends Model
{
    protected $fillable = [
        'scheduled_visit_id',
        'from_outcome',
        'to_outcome',
        'reason',
        'evidence_note',
        'reclassified_by_user_id',
        'reclassified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reclassified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ScheduledVisit, $this>
     */
    public function scheduledVisit(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reclassifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reclassified_by_user_id');
    }
}
