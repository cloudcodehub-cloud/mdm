<?php

namespace App\Models;

use App\Enums\IncidentFollowUpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'action_type',
    'assigned_employee_id',
    'due_on',
    'status',
    'completion_note',
    'completed_at',
    'created_by_user_id',
    'completed_by_user_id',
])]
class IncidentFollowUp extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IncidentFollowUpStatus::class,
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === IncidentFollowUpStatus::Open;
    }
}
