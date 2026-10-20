<?php

namespace App\Models;

use App\Enums\PayrollPeriodAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_period_id
 * @property PayrollPeriodAuditAction $action
 * @property int $actor_user_id
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 */
class PayrollPeriodAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payroll_period_id',
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
            'action' => PayrollPeriodAuditAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
