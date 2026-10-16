<?php

namespace App\Services;

use App\Enums\ComplianceVerificationStatus;
use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\InAppNotification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ComplianceExpirationReminderService
{
    /** @var list<int> */
    public const MILESTONES = [30, 15, 5, 0];

    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * @return array{credentials: int, trainings: int}
     */
    public function dispatchDue(?CarbonInterface $now = null): array
    {
        $today = ($now ?? $this->settings->now())->timezone($this->settings->timezone())->toDateString();
        $counts = ['credentials' => 0, 'trainings' => 0];

        EmployeeCredential::query()
            ->with(['employee.user', 'employee.supervisor.user'])
            ->where('verification_status', ComplianceVerificationStatus::Verified)
            ->whereNotNull('expires_on')
            ->orderBy('id')
            ->each(function (EmployeeCredential $credential) use ($today, &$counts): void {
                $counts['credentials'] += $this->dispatchForEvidence(
                    $credential->employee,
                    'credential',
                    $credential->id,
                    $credential->name,
                    $credential->expires_on?->toDateString(),
                    $today,
                    route('employees.show', $credential->employee),
                ) ? 1 : 0;
            });

        EmployeeTraining::query()
            ->with(['employee.user', 'employee.supervisor.user'])
            ->where('verification_status', ComplianceVerificationStatus::Verified)
            ->whereNotNull('expires_on')
            ->orderBy('id')
            ->each(function (EmployeeTraining $training) use ($today, &$counts): void {
                $counts['trainings'] += $this->dispatchForEvidence(
                    $training->employee,
                    'training',
                    $training->id,
                    $training->title,
                    $training->expires_on?->toDateString(),
                    $today,
                    route('employees.show', $training->employee),
                ) ? 1 : 0;
            });

        return $counts;
    }

    private function dispatchForEvidence(
        ?Employee $employee,
        string $kind,
        int $recordId,
        string $title,
        ?string $expiresOn,
        string $today,
        string $url,
    ): bool {
        if ($employee === null || $expiresOn === null) {
            return false;
        }

        $daysRemaining = (int) round(
            (strtotime($expiresOn.' 12:00:00') - strtotime($today.' 12:00:00')) / 86400,
        );

        if (! in_array($daysRemaining, self::MILESTONES, true)) {
            return false;
        }

        $created = false;
        foreach ($this->recipients($employee) as $user) {
            $sourceKey = sprintf(
                'compliance.%s.%d.milestone.%d.%s',
                $kind,
                $recordId,
                $daysRemaining,
                $expiresOn,
            );

            $existing = InAppNotification::query()
                ->where('user_id', $user->id)
                ->where('source_key', $sourceKey)
                ->exists();

            [$headline, $body] = $this->copy($employee, $title, $expiresOn, $daysRemaining);

            $this->notifications->notify(
                $user,
                InAppNotificationType::Compliance,
                $headline,
                $body,
                $sourceKey,
                $url,
            );

            if (! $existing) {
                $created = true;
            }
        }

        return $created;
    }

    /**
     * @return list<User>
     */
    private function recipients(Employee $employee): array
    {
        /** @var Collection<int, User> $users */
        $users = collect();

        if ($employee->user !== null) {
            $users->put($employee->user->id, $employee->user);
        }

        $supervisorUser = $employee->supervisor?->user;
        if ($supervisorUser !== null) {
            $users->put($supervisorUser->id, $supervisorUser);
        }

        User::query()
            ->where('role', Role::Admin)
            ->orderBy('id')
            ->get()
            ->each(fn (User $admin) => $users->put($admin->id, $admin));

        return $users->values()->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copy(Employee $employee, string $title, string $expiresOn, int $daysRemaining): array
    {
        if ($daysRemaining === 0) {
            return [
                'Compliance expired',
                sprintf('%s · %s expired on %s. This is an expiration reminder, not by itself a Hard Stop.', $employee->full_name, $title, $expiresOn),
            ];
        }

        return [
            sprintf('Compliance expires in %d days', $daysRemaining),
            sprintf(
                '%s · %s expires on %s (%d days). Expiring Soon is a warning only unless a Hard Stop requirement applies.',
                $employee->full_name,
                $title,
                $expiresOn,
                $daysRemaining,
            ),
        ];
    }
}
