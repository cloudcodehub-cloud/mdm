<?php

namespace App\Services;

use App\Enums\ComplianceDateStatus;
use App\Enums\CredentialStatus;
use App\Enums\TrainingStatus;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;

class ComplianceStatusService
{
    public function __construct(private SettingsService $settings) {}

    public function forCredential(EmployeeCredential $credential): ComplianceDateStatus
    {
        if ($credential->status === CredentialStatus::Revoked) {
            return ComplianceDateStatus::Revoked;
        }

        if ($this->isExpired($credential->expires_on?->toDateString(), $credential->status === CredentialStatus::Expired)) {
            return ComplianceDateStatus::Expired;
        }

        if ($credential->status === CredentialStatus::Pending) {
            return ComplianceDateStatus::Pending;
        }

        if ($credential->status !== CredentialStatus::Active) {
            return ComplianceDateStatus::Expired;
        }

        if ($this->isExpiringSoon($credential->expires_on?->toDateString())) {
            return ComplianceDateStatus::ExpiringSoon;
        }

        return ComplianceDateStatus::Valid;
    }

    public function forTraining(EmployeeTraining $training): ComplianceDateStatus
    {
        if ($this->isExpired($training->expires_on?->toDateString(), $training->status === TrainingStatus::Expired)) {
            return ComplianceDateStatus::Expired;
        }

        if ($training->status === TrainingStatus::InProgress) {
            return ComplianceDateStatus::InProgress;
        }

        if ($training->status !== TrainingStatus::Completed) {
            return ComplianceDateStatus::Expired;
        }

        if ($this->isExpiringSoon($training->expires_on?->toDateString())) {
            return ComplianceDateStatus::ExpiringSoon;
        }

        return ComplianceDateStatus::Valid;
    }

    public function isExpired(?string $expiresOn, bool $storedExpired): bool
    {
        if ($expiresOn === null) {
            return $storedExpired;
        }

        return $expiresOn < $this->settings->today();
    }

    public function isExpiringSoon(?string $expiresOn): bool
    {
        if ($expiresOn === null) {
            return false;
        }

        $today = $this->settings->today();

        if ($expiresOn < $today) {
            return false;
        }

        $threshold = $this->settings->localNow()->addDays($this->settings->credentialExpiringSoonDays())->toDateString();

        return $expiresOn <= $threshold;
    }
}
