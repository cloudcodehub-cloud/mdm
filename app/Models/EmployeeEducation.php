<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Database\Factories\EmployeeEducationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property EducationLevel $level
 * @property string|null $institution_name
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property bool|null $graduated
 * @property int|null $years_completed
 * @property string|null $degree
 * @property int $sort_order
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'level',
    'institution_name',
    'city',
    'state',
    'country',
    'graduated',
    'years_completed',
    'degree',
    'sort_order',
])]
class EmployeeEducation extends Model
{
    /** @use HasFactory<EmployeeEducationFactory> */
    use HasFactory;

    protected $table = 'employee_educations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => EducationLevel::class,
            'graduated' => 'boolean',
            'years_completed' => 'integer',
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
        return filled($this->institution_name);
    }
}
