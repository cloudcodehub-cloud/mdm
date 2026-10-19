<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Support\PrefixedNumber;
use Database\Factories\ClientFactory;
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
 * @property string $client_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $date_of_birth
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
 * @property string|null $emergency_contact_phone
 * @property ClientStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $status_changed_at
 * @property int|null $status_changed_by_user_id
 * @property Carbon|null $archive_pending_at
 * @property int|null $archive_pending_by_user_id
 * @property string|null $archive_pending_reason
 * @property array<int, array<string, mixed>>|null $archive_pending_blockers
 * @property string|null $archive_pending_from_status
 * @property int|null $supervisor_id
 * @property string|null $notes
 * @property string|null $profile_photo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Employee|null $supervisor
 * @property-read User|null $statusChangedBy
 * @property-read Collection<int, ClientAuthorization> $authorizations
 * @property-read Collection<int, CarePlan> $carePlans
 * @property-read Collection<int, CareService> $careServices
 * @property-read Collection<int, ScheduledVisit> $scheduledVisits
 * @property-read Collection<int, Visit> $visits
 * @property-read Collection<int, ClientCriticalAlert> $criticalAlerts
 * @property-read Collection<int, ClientContact> $contacts
 * @property-read Collection<int, ClientServiceLocation> $serviceLocations
 * @property-read Collection<int, ClientAdminHistoryEvent> $adminHistoryEvents
 *
 * @method static Builder<static> visibleTo(User $user)
 * @method static Builder<static> search(?string $term)
 */
#[Fillable([
    'client_number',
    'first_name',
    'middle_name',
    'last_name',
    'email',
    'phone',
    'date_of_birth',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_phone',
    'status',
    'status_reason',
    'status_changed_at',
    'status_changed_by_user_id',
    'supervisor_id',
    'notes',
    'profile_photo_path',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'status' => ClientStatus::class,
            'status_changed_at' => 'datetime',
            'archive_pending_at' => 'datetime',
            'archive_pending_blockers' => 'array',
        ];
    }

    public function isArchivePending(): bool
    {
        return $this->status === ClientStatus::ArchivePending;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function archivePendingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_pending_by_user_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by_user_id');
    }

    /**
     * @return HasMany<ClientDspAssignment, $this>
     */
    public function dspAssignments(): HasMany
    {
        return $this->hasMany(ClientDspAssignment::class);
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function assignedDsps(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'client_dsp_assignments')
            ->withPivot(['status', 'started_on', 'ended_on', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ClientAuthorization, $this>
     */
    public function authorizations(): HasMany
    {
        return $this->hasMany(ClientAuthorization::class);
    }

    /**
     * @return HasMany<CarePlan, $this>
     */
    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    /**
     * @return BelongsToMany<CareService, $this>
     */
    public function careServices(): BelongsToMany
    {
        return $this->belongsToMany(CareService::class, 'client_care_services')
            ->withTimestamps()
            ->orderByPivot('id');
    }

    /**
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
     * @return HasMany<ClientCriticalAlert, $this>
     */
    public function criticalAlerts(): HasMany
    {
        return $this->hasMany(ClientCriticalAlert::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * @return HasMany<ClientServiceLocation, $this>
     */
    public function serviceLocations(): HasMany
    {
        return $this->hasMany(ClientServiceLocation::class)->orderByDesc('is_primary')->orderBy('label');
    }

    /**
     * @return HasMany<ClientAdminHistoryEvent, $this>
     */
    public function adminHistoryEvents(): HasMany
    {
        return $this->hasMany(ClientAdminHistoryEvent::class)->orderByDesc('created_at');
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

    public static function nextClientNumber(): string
    {
        return PrefixedNumber::next(self::withTrashed(), 'client_number', 'CLT-');
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
            return $query->where('supervisor_id', $user->employee->id);
        }

        if ($user->isDsp() && $user->employee) {
            return $query->whereIn('id', $user->employee->clientAssignments()->active()->select('client_id'));
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
            $builder->where('client_number', 'like', '%'.$term.'%')
                ->orWhere('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%');
        });
    }
}
