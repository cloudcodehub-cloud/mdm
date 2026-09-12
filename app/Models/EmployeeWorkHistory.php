<?php

namespace App\Models;

use Database\Factories\EmployeeWorkHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon|null $started_on
 * @property Carbon|null $ended_on
 * @property string|null $job_title
 * @property string|null $employer
 * @property string|null $employer_phone
 * @property string|null $employer_address
 * @property string|null $reason_for_leaving
 * @property string|null $job_duties
 * @property int $sort_order
 *
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id',
    'started_on',
    'ended_on',
    'job_title',
    'employer',
    'employer_phone',
    'employer_address',
    'reason_for_leaving',
    'job_duties',
    'sort_order',
])]
class EmployeeWorkHistory extends Model
{
    /** @use HasFactory<EmployeeWorkHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
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
        return filled($this->employer) && filled($this->job_title);
    }
}
