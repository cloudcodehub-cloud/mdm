<?php

namespace App\Services;

use App\Enums\ComplianceVerificationStatus;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplianceEvidenceVerificationService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * DSP or Admin submits replacement/new evidence for review. Non-authoritative until verified.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitCredential(User $actor, Employee $employee, array $data): EmployeeCredential
    {
        $this->assertCanSubmit($actor, $employee);

        return EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'type' => CredentialType::from((string) $data['type']),
            'name' => trim((string) $data['name']),
            'issuer' => filled($data['issuer'] ?? null) ? trim((string) $data['issuer']) : null,
            'credential_number' => filled($data['credential_number'] ?? null) ? trim((string) $data['credential_number']) : null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'status' => CredentialStatus::Pending,
            'verification_status' => ComplianceVerificationStatus::PendingReview,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitTraining(User $actor, Employee $employee, array $data): EmployeeTraining
    {
        $this->assertCanSubmit($actor, $employee);

        return EmployeeTraining::query()->create([
            'employee_id' => $employee->id,
            'title' => trim((string) $data['title']),
            'provider' => filled($data['provider'] ?? null) ? trim((string) $data['provider']) : null,
            'completed_on' => $data['completed_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'hours' => $data['hours'] ?? null,
            'status' => TrainingStatus::InProgress,
            'verification_status' => ComplianceVerificationStatus::PendingReview,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
    }

    public function verifyCredential(User $actor, EmployeeCredential $credential, ?string $note = null): EmployeeCredential
    {
        $this->assertCanVerify($actor, $credential->employee);

        if ($credential->verification_status === ComplianceVerificationStatus::Verified
            && $credential->status === CredentialStatus::Active) {
            throw ValidationException::withMessages([
                'credential' => 'This credential is already verified.',
            ]);
        }

        return DB::transaction(function () use ($actor, $credential, $note): EmployeeCredential {
            $credential->update([
                'status' => CredentialStatus::Active,
                'verification_status' => ComplianceVerificationStatus::Verified,
                'verified_by_user_id' => $actor->id,
                'verified_at' => $this->settings->now(),
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'verification_note' => filled($note) ? trim($note) : null,
            ]);

            return $credential->refresh();
        });
    }

    public function rejectCredential(User $actor, EmployeeCredential $credential, string $reason): EmployeeCredential
    {
        $this->assertCanVerify($actor, $credential->employee);

        if ($credential->verification_status !== ComplianceVerificationStatus::PendingReview) {
            throw ValidationException::withMessages([
                'credential' => 'Only Pending Review evidence can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($actor, $credential, $reason): EmployeeCredential {
            $credential->update([
                'status' => CredentialStatus::Pending,
                'verification_status' => ComplianceVerificationStatus::Rejected,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => $this->settings->now(),
                'verification_note' => trim($reason),
            ]);

            return $credential->refresh();
        });
    }

    public function verifyTraining(User $actor, EmployeeTraining $training, ?string $note = null): EmployeeTraining
    {
        $this->assertCanVerify($actor, $training->employee);

        if ($training->verification_status === ComplianceVerificationStatus::Verified
            && $training->status === TrainingStatus::Completed) {
            throw ValidationException::withMessages([
                'training' => 'This training is already verified.',
            ]);
        }

        return DB::transaction(function () use ($actor, $training, $note): EmployeeTraining {
            $training->update([
                'status' => TrainingStatus::Completed,
                'verification_status' => ComplianceVerificationStatus::Verified,
                'verified_by_user_id' => $actor->id,
                'verified_at' => $this->settings->now(),
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'verification_note' => filled($note) ? trim($note) : null,
                'completed_on' => $training->completed_on ?? $this->settings->today(),
            ]);

            return $training->refresh();
        });
    }

    public function rejectTraining(User $actor, EmployeeTraining $training, string $reason): EmployeeTraining
    {
        $this->assertCanVerify($actor, $training->employee);

        if ($training->verification_status !== ComplianceVerificationStatus::PendingReview) {
            throw ValidationException::withMessages([
                'training' => 'Only Pending Review evidence can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($actor, $training, $reason): EmployeeTraining {
            $training->update([
                'status' => TrainingStatus::InProgress,
                'verification_status' => ComplianceVerificationStatus::Rejected,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => $this->settings->now(),
                'verification_note' => trim($reason),
            ]);

            return $training->refresh();
        });
    }

    private function assertCanSubmit(User $actor, Employee $employee): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->isDsp() && $actor->employee?->is($employee)) {
            return;
        }

        throw ValidationException::withMessages([
            'evidence' => 'You may only submit compliance evidence for your own employee record.',
        ]);
    }

    private function assertCanVerify(User $actor, Employee $employee): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'evidence' => 'Only Admin / authorized HR-compliance management may verify or reject compliance evidence.',
        ]);
    }
}
