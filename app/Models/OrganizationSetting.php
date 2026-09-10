<?php

namespace App\Models;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $organization_name
 * @property string $timezone
 * @property DateFormat $date_format
 * @property TimeFormat $time_format
 * @property int $first_day_of_week
 * @property int $credential_expiring_soon_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_name',
    'timezone',
    'date_format',
    'time_format',
    'first_day_of_week',
    'credential_expiring_soon_days',
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
        ];
    }
}
