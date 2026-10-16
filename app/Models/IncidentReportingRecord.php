<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'reported_to',
    'person_organization',
    'reported_at',
    'reported_by_employee_id',
    'category',
    'method',
    'reference_number',
    'note',
    'created_by_user_id',
])]
class IncidentReportingRecord extends Model
{
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
