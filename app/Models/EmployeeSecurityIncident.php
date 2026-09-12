<?php

namespace App\Models;

use Database\Factories\EmployeeSecurityIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property string|null $incident
 * @property string|null $city_state
 * @property string|null $charge
 * @property int $sort_order
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'incident',
    'city_state',
    'charge',
    'sort_order',
])]
class EmployeeSecurityIncident extends Model
{
    /** @use HasFactory<EmployeeSecurityIncidentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
