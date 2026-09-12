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
    public function dashboardItems(User $user, int $limit = 8): array
    {
        $items = collect();

        if ($user->isAdmin() || $user->isSupervisor()) {
            $employees = Employee::query()
                ->visibleTo($user)
                ->where('employment_status', '!=', EmploymentStatus::Terminated)
                ->with(['credentials', 'educations', 'personalReferences', 'workHistories', 'weeklyAvailabilities', 'user'])
                ->orderBy('last_name')
                ->get();

            foreach ($employees as $employee) {
                $report = $this->completion->forEmployee($employee);

                if (! $this->completion->needsAttention($report, $employee->employment_status)) {
                    continue;
                }

                $items->push([
                    'id' => 'employee-'.$employee->id,
                    'kind' => 'employee',
                    'name' => $employee->full_name,
                    'percent' => $report['percent'],
                    'summary' => $report['summary'],
                    'href' => route('employees.show', $employee),
                    'photo_url' => app(ProfilePhotoService::class)->employeeUrl($employee),
                    'initials' => $employee->initials(),
                ]);
            }

            $clients = Client::query()
                ->visibleTo($user)
                ->where('status', '!=', ClientStatus::Discharged)
                ->with(['careServices', 'carePlans', 'authorizations', 'dspAssignments'])
                ->orderBy('last_name')
                ->get();

            foreach ($clients as $client) {
                $report = $this->completion->forClient($client);

                if (! $this->completion->needsAttention($report, $client->status)) {
                    continue;
                }

                $items->push([
                    'id' => 'client-'.$client->id,
                    'kind' => 'client',
                    'name' => $client->full_name,
                    'percent' => $report['percent'],
                    'summary' => $report['summary'],
                    'href' => route('clients.show', $client),
                    'photo_url' => app(ProfilePhotoService::class)->clientUrl($client),
                    'initials' => $client->initials(),
                ]);
            }
        } elseif ($user->isDsp() && $user->employee) {
            $report = $this->completion->forEmployee($user->employee);

            if ($this->completion->needsAttention($report, $user->employee->employment_status)) {
                $items->push([
                    'id' => 'employee-'.$user->employee->id,
                    'kind' => 'employee',
                    'name' => $user->employee->full_name,
                    'percent' => $report['percent'],
                    'summary' => $report['summary'],
                    'href' => route('employees.show', $user->employee),
                    'photo_url' => app(ProfilePhotoService::class)->employeeUrl($user->employee),
                    'initials' => $user->employee->initials(),
                ]);
            }
        }

        $ranked = [];

        foreach ($items->sortBy('percent')->take($limit) as $item) {
            if (is_array($item)) {
                $ranked[] = $item;
            }
        }

        return $ranked;
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
