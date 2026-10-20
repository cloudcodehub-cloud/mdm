<?php

namespace App\Models;

use App\Enums\TemporarySupervisorCoverageAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $temporary_supervisor_coverage_id
 * @property TemporarySupervisorCoverageAuditAction $action
 * @property int $actor_user_id
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 */
class TemporarySupervisorCoverageAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'temporary_supervisor_coverage_id',
        'action',
        'actor_user_id',
        'reason',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => TemporarySupervisorCoverageAuditAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TemporarySupervisorCoverage, $this>
     */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(TemporarySupervisorCoverage::class, 'temporary_supervisor_coverage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
