<?php

namespace App\Models;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Services\SettingsService;
use Database\Factories\EmployeeCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property CredentialType $type
 * @property string $name
 * @property string|null $issuer
 * @property string|null $credential_number
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property CredentialStatus $status
 * @property string|null $notes
 * @property string|null $document_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 *
 * @method static Builder<static> currentlyValid()
 */
#[Fillable([
    'employee_id',
    'type',
    'name',
    'issuer',
    'credential_number',
    'issued_on',
    'expires_on',
    'status',
    'notes',
    'document_path',
])]
class EmployeeCredential extends Model
{
    /** @use HasFactory<EmployeeCredentialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CredentialType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'status' => CredentialStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isExpired(?Carbon $on = null): bool
    {
        if ($this->expires_on === null) {
            return $this->status === CredentialStatus::Expired;
        }

        return $this->expires_on->toDateString() < ($on?->toDateString() ?? app(SettingsService::class)->today());
    }

    public function isCurrentlyValid(?Carbon $on = null): bool
    {
        if ($this->status !== CredentialStatus::Active) {
            return false;
        }

        return ! $this->isExpired($on);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where('status', CredentialStatus::Active)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_on')
                    ->orWhereDate('expires_on', '>=', app(SettingsService::class)->today());
            });
    }
}
