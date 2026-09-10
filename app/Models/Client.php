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
 * @property int|null $supervisor_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Employee|null $supervisor
 * @property-read Collection<int, ClientAuthorization> $authorizations
 * @property-read Collection<int, CarePlan> $carePlans
 * @property-read Collection<int, ScheduledVisit> $scheduledVisits
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
    'supervisor_id',
    'notes',
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
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
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
     * @return HasMany<ScheduledVisit, $this>
     */
    public function scheduledVisits(): HasMany
    {
        return $this->hasMany(ScheduledVisit::class);
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
