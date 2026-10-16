<?php

namespace App\Services;

use App\Models\ComplianceRequirement;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplianceExceptionService
{
    public function __construct(
        private SettingsService $settings,
        private ComplianceStaffingService $complianceStaffing,
    ) {}

    /**
     * @param  array{reason: string, starts_at: string, ends_at: string}  $data
     */
    public function apply(
        User $actor,
        Employee $employee,
        ComplianceRequirement $requirement,
        array $data,
    ): ComplianceTemporaryException {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'compliance_requirement_id' => 'Only Admin / authorized HR-compliance management may apply a Hard Stop temporary exception.',
            ]);
        }

        if (! $requirement->is_active) {
            throw ValidationException::withMessages([
                'compliance_requirement_id' => 'This compliance requirement is not active.',
            ]);
        }

        if (! $requirement->isHardStop()) {
            throw ValidationException::withMessages([
                'compliance_requirement_id' => 'Temporary exceptions apply only to Hard Stop / Mandatory for Service requirements.',
            ]);
        }

        if (! $requirement->allowsTemporaryException()) {
            throw ValidationException::withMessages([
                'compliance_requirement_id' => 'This Hard Stop requirement is Non-Overrideable. Admin cannot bypass it.',
            ]);
        }

        $startsAt = Carbon::parse($data['starts_at'], $this->settings->timezone())->utc();
        $endsAt = Carbon::parse($data['ends_at'], $this->settings->timezone())->utc();

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The temporary exception end must be after the start.',
            ]);
        }

        return DB::transaction(function () use ($actor, $employee, $requirement, $data, $startsAt, $endsAt): ComplianceTemporaryException {
            $exception = ComplianceTemporaryException::query()->create([
                'employee_id' => $employee->id,
                'compliance_requirement_id' => $requirement->id,
                'reason' => trim($data['reason']),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by_user_id' => $actor->id,
            ]);

            $this->complianceStaffing->syncForEmployee($employee, $actor, notify: false);

            return $exception;
        });
    }

    public function revoke(
        User $actor,
        ComplianceTemporaryException $exception,
        string $reason,
    ): ComplianceTemporaryException {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'exception' => 'Only Admin / authorized HR-compliance management may revoke a temporary exception.',
            ]);
        }

        if ($exception->revoked_at !== null) {
            throw ValidationException::withMessages([
                'exception' => 'This temporary exception is already revoked.',
            ]);
        }

        $exception->update([
            'revoked_at' => $this->settings->now(),
            'revoked_by_user_id' => $actor->id,
            'revoke_reason' => trim($reason),
        ]);

        $exception = $exception->refresh();
        $exception->loadMissing('employee');
        if ($exception->employee !== null) {
            $this->complianceStaffing->syncForEmployee($exception->employee, $actor);
        }

        return $exception;
    }
}
