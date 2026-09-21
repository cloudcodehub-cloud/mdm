<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

class ProfileAttentionService
{
    public function __construct(
        private ProfileCompletionService $completion,
        private InAppNotificationService $notifications,
    ) {}

    public function syncEmployee(Employee $employee): void
    {
        $employee->loadMissing('supervisor.user');
        $report = $this->completion->forEmployee($employee);

        if (! $this->completion->needsAttention($report, $employee->employment_status)) {
            return;
        }

        $summary = is_string($report['summary'] ?? null) && $report['summary'] !== ''
            ? $report['summary']
            : 'Profile details remaining';

        $this->notifyRecipients(
            $this->employeeRecipients($employee),
            'Employee profile needs attention',
            $employee->full_name.' — '.$report['percent'].'% complete. '.$summary,
            'profile-employee-'.$employee->id,
            route('employees.show', $employee),
        );
    }

    public function syncClient(Client $client): void
    {
        $client->loadMissing('supervisor.user');
        $report = $this->completion->forClient($client);

        if (! $this->completion->needsAttention($report, $client->status)) {
            return;
        }

        $summary = is_string($report['summary'] ?? null) && $report['summary'] !== ''
            ? $report['summary']
            : 'Profile details remaining';

        $this->notifyRecipients(
            $this->clientRecipients($client),
            'Client profile needs attention',
            $client->full_name.' — '.$report['percent'].'% complete. '.$summary,
            'profile-client-'.$client->id,
            route('clients.show', $client),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return array{employees: list<array<string, mixed>>, clients: list<array<string, mixed>>}
     */
    public function dashboardGroups(User $user, int $limit = 8): array
    {
        if (! $user->isAdmin() && ! $user->isSupervisor()) {
            return [
                'employees' => [],
                'clients' => [],
            ];
        }

        $employees = [];
        $clients = [];

        foreach (Employee::query()
            ->visibleTo($user)
            ->where('employment_status', '!=', EmploymentStatus::Terminated)
            ->with(['credentials', 'educations', 'personalReferences', 'workHistories', 'weeklyAvailabilities', 'user'])
            ->orderBy('last_name')
            ->get() as $employee) {
            $report = $this->completion->forEmployee($employee);

            if (! $this->completion->needsAttention($report, $employee->employment_status)) {
                continue;
            }

            $employees[] = [
                'id' => 'employee-'.$employee->id,
                'kind' => 'employee',
                'name' => $employee->full_name,
                'percent' => $report['percent'],
                'status' => $report['status'],
                'status_label' => $report['status_label'],
                'tone' => $report['tone'],
                'summary' => $report['summary'],
                'href' => route('employees.show', $employee),
                'photo_url' => app(ProfilePhotoService::class)->employeeUrl($employee),
                'initials' => $employee->initials(),
            ];
        }

        foreach (Client::query()
            ->visibleTo($user)
            ->where('status', '!=', ClientStatus::Discharged)
            ->with(['careServices', 'carePlans', 'authorizations', 'dspAssignments'])
            ->orderBy('last_name')
            ->get() as $client) {
            $report = $this->completion->forClient($client);

            if (! $this->completion->needsAttention($report, $client->status)) {
                continue;
            }

            $clients[] = [
                'id' => 'client-'.$client->id,
                'kind' => 'client',
                'name' => $client->full_name,
                'percent' => $report['percent'],
                'status' => $report['status'],
                'status_label' => $report['status_label'],
                'tone' => $report['tone'],
                'summary' => $report['summary'],
                'href' => route('clients.show', $client),
                'photo_url' => app(ProfilePhotoService::class)->clientUrl($client),
                'initials' => $client->initials(),
            ];
        }

        return [
            'employees' => $this->rankAttentionItems($employees, $limit),
            'clients' => $this->rankAttentionItems($clients, $limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dashboardItems(User $user, int $limit = 8): array
    {
        $groups = $this->dashboardGroups($user, $limit);

        return $this->rankAttentionItems([
            ...$groups['employees'],
            ...$groups['clients'],
        ], $limit);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function rankAttentionItems(array $items, int $limit): array
    {
        usort($items, function (array $left, array $right): int {
            $toneRank = static fn (array $item): int => match ($item['tone'] ?? '') {
                'danger' => 0,
                'warning' => 1,
                default => 2,
            };

            return [$toneRank($left), $left['percent'] ?? 100] <=> [$toneRank($right), $right['percent'] ?? 100];
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notifyRecipients(Collection $recipients, string $title, string $body, string $sourceKey, string $url): void
    {
        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                InAppNotificationType::Profile,
                $title,
                $body,
                $sourceKey.'-user-'.$recipient->id,
                $url,
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function employeeRecipients(Employee $employee): Collection
    {
        $users = User::query()->where('role', Role::Admin)->get();

        if ($employee->supervisor?->user) {
            $users->push($employee->supervisor->user);
        }

        if ($employee->user && $employee->user->isDsp()) {
            $users->push($employee->user);
        }

        return $users->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function clientRecipients(Client $client): Collection
    {
        $users = User::query()->where('role', Role::Admin)->get();

        if ($client->supervisor?->user) {
            $users->push($client->supervisor->user);
        }

        return $users->unique('id')->values();
    }
}
