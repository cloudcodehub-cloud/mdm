<?php

namespace App\Models;

use App\Enums\AttendanceCorrectionStatus;
use Database\Factories\AttendanceCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scheduled_visit_id
 * @property int|null $visit_id
 * @property AttendanceCorrectionStatus $status
 * @property bool $is_evv_reconstruction
 * @property bool $overlap_conflict
 * @property list<string>|null $proposed_fields
 * @property string|null $original_attendance_status
 * @property list<array<string, mixed>>|null $task_corrections
 * @property Carbon|null $original_clocked_in_at
 * @property Carbon|null $original_clocked_out_at
 * @property Carbon|null $requested_clocked_in_at
 * @property Carbon|null $requested_clocked_out_at
 * @property string|null $original_visit_notes
 * @property string|null $requested_visit_notes
 * @property string|null $original_handover_note
 * @property string|null $requested_handover_note
 * @property string|null $original_clock_in_location_status
 * @property string|null $requested_clock_in_location_status
 * @property string|null $original_clock_in_unavailable_reason
 * @property string|null $requested_clock_in_unavailable_reason
 * @property string|null $original_clock_out_location_status
 * @property string|null $requested_clock_out_location_status
 * @property string|null $original_clock_out_unavailable_reason
 * @property string|null $requested_clock_out_unavailable_reason
 * @property string $reason
 * @property string|null $note
 * @property int $requested_by_user_id
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduledVisit $scheduledVisit
 * @property-read Visit|null $visit
 * @property-read User $requestedBy
 * @property-read User|null $reviewedBy
 *
 * @method static Builder<static> pending()
 * @method static Builder<static> approved()
 */
#[Fillable([
    'scheduled_visit_id',
    'visit_id',
    'status',
    'is_evv_reconstruction',
    'overlap_conflict',
    'proposed_fields',
    'original_attendance_status',
    'task_corrections',
    'original_clocked_in_at',
    'original_clocked_out_at',
    'requested_clocked_in_at',
    'requested_clocked_out_at',
    'original_visit_notes',
    'requested_visit_notes',
    'original_handover_note',
    'requested_handover_note',
    'original_clock_in_location_status',
    'requested_clock_in_location_status',
    'original_clock_in_unavailable_reason',
    'requested_clock_in_unavailable_reason',
    'original_clock_out_location_status',
    'requested_clock_out_location_status',
    'original_clock_out_unavailable_reason',
    'requested_clock_out_unavailable_reason',
    'reason',
    'note',
    'requested_by_user_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'applied_at',
])]
class AttendanceCorrection extends Model
{
    /** @use HasFactory<AttendanceCorrectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceCorrectionStatus::class,
            'is_evv_reconstruction' => 'boolean',
            'overlap_conflict' => 'boolean',
            'proposed_fields' => 'array',
            'task_corrections' => 'array',
            'original_clocked_in_at' => 'datetime',
            'original_clocked_out_at' => 'datetime',
            'requested_clocked_in_at' => 'datetime',
            'requested_clocked_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function proposes(string $field): bool
    {
        return in_array($field, $this->proposed_fields ?? [], true);
    }

    public function requestsTimeChange(): bool
    {
        return $this->proposes('times')
            || $this->proposes('evv_reconstruction')
            || $this->requested_clocked_in_at !== null
            || $this->requested_clocked_out_at !== null;
    }

    public function requestsNoteChange(): bool
    {
        return $this->proposes('visit_notes')
            || $this->proposes('handover_note');
    }

    public function requestsGpsContextChange(): bool
    {
        return $this->proposes('clock_in_gps_context')
            || $this->proposes('clock_out_gps_context');
    }

    public function requestsTaskChange(): bool
    {
        return $this->proposes('tasks') || ($this->task_corrections !== null && $this->task_corrections !== []);
    }

    public function isMissedReconstruction(): bool
    {
        return $this->is_evv_reconstruction
            && $this->original_attendance_status === 'missed'
            && $this->visit_id === null;
    }

    /**
     * @return BelongsTo<ScheduledVisit, $this>
     */
    public function scheduledVisit(): BelongsTo
    {
        return $this->belongsTo(ScheduledVisit::class);
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AttendanceCorrectionStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AttendanceCorrectionStatus::Approved);
    }
}
