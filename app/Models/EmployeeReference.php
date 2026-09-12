<?php

namespace App\Models;

use Database\Factories\EmployeeReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $home_phone
 * @property string|null $work_phone
 * @property string|null $relationship
 * @property int $sort_order
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'name',
    'address',
    'home_phone',
    'work_phone',
    'relationship',
    'sort_order',
])]
class EmployeeReference extends Model
{
    /** @use HasFactory<EmployeeReferenceFactory> */
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

    public function isComplete(): bool
    {
        return filled($this->name) && filled($this->relationship) && filled($this->home_phone);
    }
}
