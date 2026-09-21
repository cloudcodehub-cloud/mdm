<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Appearance;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property Appearance $appearance
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Collection<int, Announcement> $authoredAnnouncements
 * @property-read Collection<int, InAppNotification> $inAppNotifications
 *
 * @method static Builder<static> activeForMessaging()
 */
#[Fillable(['name', 'email', 'password', 'role', 'appearance'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'appearance' => Appearance::class,
        ];
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Announcement, $this>
     */
    public function authoredAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    /**
     * @return HasMany<InAppNotification, $this>
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole(Role::Supervisor);
    }

    public function isDsp(): bool
    {
        return $this->hasRole(Role::Dsp);
    }

    /**
     * Admins may sign in without an employee profile.
     * Supervisor and DSP accounts require a linked employee that is allowed to log in.
     */
    public function canAccessApplication(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $employee = $this->employee;

        if ($employee === null) {
            return false;
        }

        return $employee->employment_status->allowsLogin();
    }

    public function canMessage(): bool
    {
        return $this->canAccessApplication();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveForMessaging(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->where('role', Role::Admin)
                ->orWhereHas(
                    'employee',
                    fn (Builder $employee) => $employee->where('employment_status', EmploymentStatus::Active),
                );
        });
    }
}
