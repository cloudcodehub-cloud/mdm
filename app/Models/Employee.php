<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\JobType;
use App\Support\PrefixedNumber;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $employee_number
 * @property int|null $user_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $home_phone
 * @property string|null $cell_phone
 * @property string|null $alternate_phone
 * @property Carbon|null $date_of_birth
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $previous_address_line_1
 * @property string|null $previous_city
 * @property string|null $previous_state
 * @property string|null $previous_postal_code
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
 * @property string|null $emergency_contact_phone
 * @property Carbon|null $hired_on
 * @property Carbon|null $terminated_on
 * @property EmploymentStatus $employment_status
 * @property string|null $job_title
 * @property EmploymentType|null $employment_type
 * @property string|null $preferred_shift_type
 * @property int|null $desired_hours_per_week
 * @property bool|null $willing_long_term
 * @property bool|null $willing_short_term
 * @property bool|null $willing_pets
 * @property bool|null $willing_smoke
 * @property string|null $how_heard
 * @property string|null $employment_interest
 * @property bool|null $has_drivers_license
 * @property string|null $license_state
 * @property string|null $license_number
 * @property string|null $vehicle_make_year
 * @property string|null $insurance_company
 * @property string|null $insurance_policy_number
 * @property bool|null $has_moving_violations
 * @property string|null $moving_violations_description
 * @property bool|null $license_ever_suspended
 * @property string|null $license_suspension_explanation
 * @property bool|null $may_contact_current_employer
 * @property bool|null $ohio_resident_5_years
 * @property string|null $residence_history
 * @property bool|null $used_other_names
 * @property string|null $other_names
 * @property string|null $ssn
 * @property string|null $alternate_ssn
 * @property bool|null $has_conviction
 * @property string|null $security_comments
 * @property JobType $job_type
 * @property int|null $supervisor_id
 * @property string|null $notes
 * @property string|null $profile_photo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read User|null $user
 * @property-read Employee|null $supervisor
 * @property-read Collection<int, EmployeeCredential> $credentials
 * @property-read Collection<int, EmployeeTraining> $trainings
 * @property-read Collection<int, ScheduledVisit> $scheduledVisits
 * @property-read Collection<int, Visit> $visits
 * @property-read Collection<int, ScheduledVisit> $supervisedVisits
 * @property-read Collection<int, DspWeeklyAvailability> $weeklyAvailabilities
 * @property-read Collection<int, DspAvailabilityException> $availabilityExceptions
 * @property-read Collection<int, DspAvailabilityRequest> $availabilityRequests
 * @property-read Collection<int, EmployeeEducation> $educations
 * @property-read Collection<int, EmployeeReference> $personalReferences
 * @property-read Collection<int, EmployeeWorkHistory> $workHistories
 * @property-read Collection<int, EmployeeSecurityIncident> $securityIncidents
 * @property-read Collection<int, EmployeeTimeOff> $timeOff
 *
 * @method static Builder<static> visibleTo(User $user)
 * @method static Builder<static> search(?string $term)
 */
#[Fillable([
    'employee_number',
    'user_id',
    'first_name',
    'middle_name',
    'last_name',
    'email',
    'phone',
    'home_phone',
    'cell_phone',
    'alternate_phone',
    'date_of_birth',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'previous_address_line_1',
    'previous_city',
    'previous_state',
    'previous_postal_code',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_phone',
    'hired_on',
    'terminated_on',
    'employment_status',
    'job_title',
    'employment_type',
    'preferred_shift_type',
    'desired_hours_per_week',
    'willing_long_term',
    'willing_short_term',
    'willing_pets',
    'willing_smoke',
    'how_heard',
    'employment_interest',
    'has_drivers_license',
    'license_state',
    'license_number',
    'vehicle_make_year',
    'insurance_company',
    'insurance_policy_number',
    'has_moving_violations',
    'moving_violations_description',
    'license_ever_suspended',
    'license_suspension_explanation',
    'may_contact_current_employer',
    'ohio_resident_5_years',
    'residence_history',
    'used_other_names',
    'other_names',
    'ssn',
    'alternate_ssn',
    'has_conviction',
    'security_comments',
    'job_type',
    'supervisor_id',
    'notes',
    'profile_photo_path',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ssn',
        'alternate_ssn',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hired_on' => 'date',
            'terminated_on' => 'date',
            'employment_status' => EmploymentStatus::class,
            'job_type' => JobType::class,
            'employment_type' => EmploymentType::class,
            'desired_hours_per_week' => 'integer',
            'willing_long_term' => 'boolean',
            'willing_short_term' => 'boolean',
            'willing_pets' => 'boolean',
            'willing_smoke' => 'boolean',
            'has_drivers_license' => 'boolean',
            'has_moving_violations' => 'boolean',
            'license_ever_suspended' => 'boolean',
            'may_contact_current_employer' => 'boolean',
            'ohio_resident_5_years' => 'boolean',
            'used_other_names' => 'boolean',
            'has_conviction' => 'boolean',
            'ssn' => 'encrypted',
            'alternate_ssn' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function supervisedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'supervisor_id');
    }

    /**
     * @return HasMany<ClientDspAssignment, $this>
     */
    public function clientAssignments(): HasMany
    {
        return $this->hasMany(ClientDspAssignment::class);
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_dsp_assignments')
            ->withPivot(['status', 'started_on', 'ended_on', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<EmployeeCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(EmployeeCredential::class);
    }

    /**
     * @return HasMany<EmployeeTraining, $this>
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    /**
     * @return HasMany<EmployeeEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeReference, $this>
     */
    public function personalReferences(): HasMany
    {
        return $this->hasMany(EmployeeReference::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeWorkHistory, $this>
     */
    public function workHistories(): HasMany
    {
        return $this->hasMany(EmployeeWorkHistory::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeSecurityIncident, $this>
     */
    public function securityIncidents(): HasMany
    {
        return $this->hasMany(EmployeeSecurityIncident::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Visits assigned to this employee as the DSP.
     *
     * @return HasMany<ScheduledVisit, $this>
     */
    public function scheduledVisits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class);
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Visits listing this employee as the optional supervisor of record.
     *
     * @return HasMany<ScheduledVisit, $this>
     */
    public function supervisedVisits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class, 'supervisor_id');
    }

    /**
     * @return HasMany<DspWeeklyAvailability, $this>
     */
    public function weeklyAvailabilities(): HasMany
    {
        return $this->hasMany(DspWeeklyAvailability::class)->orderBy('weekday');
    }

    /**
     * @return HasMany<DspAvailabilityException, $this>
     */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(DspAvailabilityException::class)->orderByDesc('exception_date');
    }

    /**
     * @return HasMany<DspAvailabilityRequest, $this>
     */
    public function availabilityRequests(): HasMany
    {
        return $this->hasMany(DspAvailabilityRequest::class)->orderByDesc('submitted_at');
    }

    /**
     * @return HasMany<EmployeeTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(EmployeeTimeOff::class)->orderByDesc('starts_on');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->implode(' '));
    }

    public static function nextEmployeeNumber(): string
    {
        return PrefixedNumber::next(self::withTrashed(), 'employee_number', 'EMP-');
    }

    public function isActiveDsp(): bool
    {
        return $this->job_type === JobType::Dsp
            && $this->employment_status === EmploymentStatus::Active;
    }

    public function isActiveSupervisor(): bool
    {
        return $this->job_type === JobType::Supervisor
            && $this->employment_status === EmploymentStatus::Active;
    }

    public function hasPhoto(): bool
    {
        return filled($this->profile_photo_path);
    }

    public function initials(): string
    {
        $first = mb_substr((string) $this->first_name, 0, 1);
        $last = mb_substr((string) $this->last_name, 0, 1);

        return mb_strtoupper($first.$last);
    }

    public function primaryPhone(): ?string
    {
        return $this->cell_phone ?: $this->phone ?: $this->home_phone;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSupervisor() && $user->employee) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->where('supervisor_id', $user->employee->id)
                    ->orWhere('id', $user->employee->id);
            });
        }

        if ($user->isDsp() && $user->employee) {
            return $query->where('id', $user->employee->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('employee_number', 'like', '%'.$term.'%')
                ->orWhere('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('job_title', 'like', '%'.$term.'%');
        });
    }
}
