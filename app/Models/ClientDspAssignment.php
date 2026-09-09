<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\ClientDspAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $client_id
 * @property AssignmentStatus $status
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Client $client
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'employee_id',
    'client_id',
    'status',
    'started_on',
    'ended_on',
    'notes',
])]
class ClientDspAssignment extends Model
{
    /** @use HasFactory<ClientDspAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * DSP alias for the assigned employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function dsp(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        return $this->status === AssignmentStatus::Active && $this->ended_on === null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AssignmentStatus::Active)
            ->whereNull('ended_on');
    }
}
