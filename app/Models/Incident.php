<?php

namespace App\Models;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Clinical/service Incident (MDM 07). Distinct from HR EmployeeSecurityIncident.
 *
 * @property int $id
 * @property int $visit_id
 * @property int $client_id
 * @property int $reporter_employee_id
 * @property int|null $responsible_supervisor_id
 * @property IncidentStatus $status
 * @property string|null $tracking_number
 * @property Carbon $occurred_at
 * @property string $original_category
 * @property string|null $original_subcategory
 * @property string|null $official_category
 * @property string|null $official_subcategory
 * @property string $summary
 * @property string $immediate_action
 * @property bool $injured
 * @property string|null $injury_type
 * @property string|null $injury_body_location
 * @property string|null $injury_medical_check
 * @property string|null $injury_notes
 * @property bool $intervention
 * @property string|null $intervention_type
 * @property Carbon|null $intervention_started_at
 * @property Carbon|null $intervention_stopped_at
 * @property string|null $intervention_notes
 * @property bool $supervisor_notified
 * @property string|null $additional_notes
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $accuracy
 * @property ClockInLocationMethod|null $location_method
 * @property ClockInLocationStatus|null $location_status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by_user_id
 * @property Carbon|null $acknowledgment_overdue_notified_at
 * @property Carbon|null $discarded_at
 * @property int|null $discarded_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Visit $visit
 * @property-read Client $client
 * @property-read Employee $reporter
 * @property-read Employee|null $responsibleSupervisor
 * @property-read User|null $acknowledgedBy
 * @property-read User|null $discardedBy
 * @property-read Collection<int, IncidentHistoryEvent> $historyEvents
 */
#[Fillable([
    'visit_id',
    'client_id',
    'reporter_employee_id',
    'responsible_supervisor_id',
    'status',
    'tracking_number',
    'occurred_at',
    'original_category',
    'original_subcategory',
    'official_category',
    'official_subcategory',
    'finding',
    'cause_contributing_factors',
    'prevention_plan',
    'summary',
    'immediate_action',
    'injured',
    'injury_type',
    'injury_body_location',
    'injury_medical_check',
    'injury_notes',
    'injury_occurred_at',
    'intervention',
    'intervention_type',
    'intervention_started_at',
    'intervention_stopped_at',
    'intervention_notes',
    'supervisor_notified',
    'additional_notes',
    'latitude',
    'longitude',
    'accuracy',
    'location_method',
    'location_status',
    'submitted_at',
    'acknowledged_at',
    'acknowledged_by_user_id',
    'acknowledgment_overdue_notified_at',
    'closed_at',
    'closed_by_user_id',
    'closure_note',
    'original_closed_at',
    'original_closed_by_user_id',
    'original_closure_note',
    'reopened_at',
    'reopened_by_user_id',
    'reopen_reason',
    'discarded_at',
    'discarded_by_user_id',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'occurred_at' => 'datetime',
            'injured' => 'boolean',
            'intervention' => 'boolean',
            'supervisor_notified' => 'boolean',
            'injury_occurred_at' => 'datetime',
            'intervention_started_at' => 'datetime',
            'intervention_stopped_at' => 'datetime',
            'location_method' => ClockInLocationMethod::class,
            'location_status' => ClockInLocationStatus::class,
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'acknowledgment_overdue_notified_at' => 'datetime',
            'closed_at' => 'datetime',
            'original_closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function responsibleSupervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_supervisor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discarded_by_user_id');
    }

    /**
     * @return HasMany<IncidentHistoryEvent, $this>
     */
    public function historyEvents(): HasMany
    {
        return $this->hasMany(IncidentHistoryEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<IncidentParty, $this>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(IncidentParty::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<IncidentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(IncidentReview::class)->orderBy('id');
    }

    /**
     * @return HasMany<IncidentFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(IncidentFollowUp::class)->orderBy('id');
    }

    /**
     * @return HasMany<IncidentReportingRecord, $this>
     */
    public function reportingRecords(): HasMany
    {
        return $this->hasMany(IncidentReportingRecord::class)->orderBy('id');
    }

    /**
     * @return HasMany<IncidentAmendment, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(IncidentAmendment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFormal(Builder $query): Builder
    {
        return $query->whereIn('status', [
            IncidentStatus::Submitted->value,
            IncidentStatus::UnderReview->value,
            IncidentStatus::Closed->value,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveDraft(Builder $query): Builder
    {
        return $query
            ->where('status', IncidentStatus::Draft)
            ->whereNull('discarded_at');
    }

    public function isDraft(): bool
    {
        return $this->status === IncidentStatus::Draft && $this->discarded_at === null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function requiresAcknowledgment(): bool
    {
        return $this->status === IncidentStatus::Submitted && ! $this->isAcknowledged();
    }
}
