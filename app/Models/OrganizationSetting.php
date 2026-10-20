<?php

namespace App\Models;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organization_name
 * @property string|null $display_name
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $main_phone
 * @property string|null $main_email
 * @property string|null $website
 * @property string|null $logo_path
 * @property string $timezone
 * @property string|null $pending_timezone
 * @property Carbon|null $pending_timezone_effective_at
 * @property string|null $pending_timezone_reason
 * @property int|null $pending_timezone_scheduled_by
 * @property Carbon|null $pending_timezone_scheduled_at
 * @property DateFormat $date_format
 * @property TimeFormat $time_format
 * @property int $first_day_of_week
 * @property int $credential_expiring_soon_days
 * @property int $incident_acknowledgment_minutes
 * @property int $staffing_needed_urgency_hours Hours before scheduled start (MDM 04-29)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $pendingTimezoneScheduler
 */
#[Fillable([
    'organization_name',
    'display_name',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country',
    'main_phone',
    'main_email',
    'website',
    'logo_path',
    'timezone',
    'pending_timezone',
    'pending_timezone_effective_at',
    'pending_timezone_reason',
    'pending_timezone_scheduled_by',
    'pending_timezone_scheduled_at',
    'date_format',
    'time_format',
    'first_day_of_week',
    'credential_expiring_soon_days',
    'incident_acknowledgment_minutes',
    'staffing_needed_urgency_hours',
])]
class OrganizationSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_format' => DateFormat::class,
            'time_format' => TimeFormat::class,
            'first_day_of_week' => 'integer',
            'credential_expiring_soon_days' => 'integer',
            'incident_acknowledgment_minutes' => 'integer',
            'staffing_needed_urgency_hours' => 'integer',
            'pending_timezone_effective_at' => 'datetime',
            'pending_timezone_scheduled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pendingTimezoneScheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_timezone_scheduled_by');
    }
}
