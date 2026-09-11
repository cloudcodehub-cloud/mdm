<?php

namespace App\Models;

use App\Enums\VisitAssignmentKind;
use Database\Factories\ScheduledVisitAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int $employee_id
 * @property int|null $assigned_by_user_id
 * @property VisitAssignmentKind $kind
 * @property string|null $reason
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 *
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read Employee $employee
 * @property-read User|null $assignedBy
 */
#[Fillable([
    'scheduled_visit_id',
    'employee_id',
    'assigned_by_user_id',
    'kind',
    'reason',
    'assigned_at',
    'ended_at',
])]
class ScheduledVisitAssignment extends Model
{
    /** @use HasFactory<ScheduledVisitAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => VisitAssignmentKind::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
