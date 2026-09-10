<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\JobType;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientAssignmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(Client $client, array $data): ClientDspAssignment
    {
        return DB::transaction(function () use ($client, $data): ClientDspAssignment {
            $employee = Employee::query()->findOrFail((int) $data['employee_id']);

            if ($employee->job_type !== JobType::Dsp) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Only DSP employees can be assigned to a client.',
                ]);
            }

            $alreadyActive = ClientDspAssignment::query()
                ->where('client_id', $client->id)
                ->where('employee_id', $employee->id)
                ->active()
                ->exists();

            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This DSP is already actively assigned to the client.',
                ]);
            }

            return ClientDspAssignment::query()->create([
                'client_id' => $client->id,
                'employee_id' => $employee->id,
                'status' => AssignmentStatus::Active,
                'started_on' => $data['started_on'],
                'ended_on' => null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function deactivate(ClientDspAssignment $assignment, ?string $endedOn = null): ClientDspAssignment
    {
        if (! $assignment->isActive()) {
            throw ValidationException::withMessages([
                'assignment' => 'This assignment is already inactive.',
            ]);
        }

        $assignment->update([
            'status' => AssignmentStatus::Inactive,
            'ended_on' => $endedOn ?: now()->toDateString(),
        ]);

        return $assignment->fresh() ?? $assignment;
    }
}
