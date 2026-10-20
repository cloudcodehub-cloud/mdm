<?php

namespace App\Models;

use App\Enums\PayrollPeriodScope;
use App\Enums\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property PayrollPeriodScope $scope
 * @property string $scope_key
 * @property int|null $employee_id
 * @property int|null $scheduled_visit_id
 * @property PayrollPeriodStatus $status
 * @property Carbon|null $finalized_at
 * @property int|null $finalized_by_user_id
 * @property string|null $finalize_note
 * @property Carbon|null $reopened_at
 * @property int|null $reopened_by_user_id
 * @property string|null $reopen_reason
 */
class PayrollPeriod extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'scope',
        'scope_key',
        'employee_id',
        'scheduled_visit_id',
        'status',
        'finalized_at',
        'finalized_by_user_id',
        'finalize_note',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'scope' => PayrollPeriodScope::class,
            'status' => PayrollPeriodStatus::class,
            'finalized_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    public function isExportReady(): bool
    {
        return $this->status->isExportReady();
    }

    public function isReopened(): bool
    {
        return $this->status === PayrollPeriodStatus::ReopenedForCorrection;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    /**
     * @return HasMany<PayrollPeriodAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(PayrollPeriodAudit::class);
    }
}
