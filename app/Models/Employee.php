<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property Carbon|null $date_of_birth
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relationship
 * @property string|null $emergency_contact_phone
 * @property Carbon|null $hired_on
 * @property Carbon|null $terminated_on
 * @property EmploymentStatus $employment_status
 * @property string|null $job_title
 * @property JobType $job_type
 * @property int|null $supervisor_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read User|null $user
 * @property-read Employee|null $supervisor
 */
#[Fillable([
    'employee_number',
    'user_id',
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
    'hired_on',
    'terminated_on',
    'employment_status',
    'job_title',
    'job_type',
    'supervisor_id',
    'notes',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

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
}
