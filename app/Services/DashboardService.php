<?php

namespace App\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\ClientStatus;
use App\Enums\CredentialStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TrainingStatus;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Support\DirectoryPresenter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private VisitClockInService $clockIn) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $today = now()->startOfDay();
        $employee = $user->employee;

        return [
            'role' => $user->role->value,
            'greeting_name' => $employee !== null ? $employee->first_name : $user->name,
            'today' => $today->toDateString(),
            'metrics' => $this->metrics($user, $employee, $today),
            'today_visits' => $this->serializeVisits($this->todayVisits($user, $today)),
            'upcoming_visits' => $this->serializeVisits($this->upcomingVisits($user, $today)),
            'assigned_dsps' => $this->assignedDsps($user, $employee),
            'assigned_clients' => $this->assignedClients($user, $employee),
            'attention_items' => $this->attentionItems($user, $employee),
            'activity' => $this->activity($user),
            'active_visit' => $this->activeVisit($user, $employee),
            'clock_in_visit' => $this->clockInVisit($user, $employee),
        ];
    }

    /**
     * @return list<array{key: string, label: string, value: int, hint: string}>
     */
    private function metrics(User $user, ?Employee $employee, CarbonInterface $today): array
    {
        if ($user->isAdmin()) {
            return [
                $this->metric('active_employees', 'Active employees', Employee::query()->where('employment_status', EmploymentStatus::Active)->count(), 'Currently employed workforce'),
                $this->metric('active_clients', 'Active clients', Client::query()->where('status', ClientStatus::Active)->count(), 'Clients currently receiving services'),
                $this->metric('visits_today', 'Scheduled visits today', $this->visitQuery($user)->whereDate('service_date', $today)->count(), 'Open scheduled visits for today'),
                $this->metric('compliance_attention', 'Credential attention', $this->credentialAttentionQuery($user, $employee)->count() + $this->trainingAttentionQuery($user, $employee)->count(), 'Expired, pending, or in-progress items'),
            ];
        }

        if ($user->isSupervisor()) {
            return [
                $this->metric('assigned_dsps', 'Assigned DSPs', $this->dspReportsQuery($employee)->count(), 'Active DSP reports'),
                $this->metric('assigned_clients', 'Assigned clients', $this->supervisedClientsQuery($employee)->count(), 'Active clients on this caseload'),
                $this->metric('visits_today', "Today's scheduled visits", $this->visitQuery($user)->whereDate('service_date', $today)->count(), 'Visits for assigned DSPs or clients'),
                $this->metric('operational_attention', 'Attention items', count($this->attentionItems($user, $employee)), 'Compliance and schedule exceptions'),
            ];
        }

        return [
            $this->metric('visits_today', "Today's visits", $this->visitQuery($user)->whereDate('service_date', $today)->count(), 'Your scheduled visits for today'),
            $this->metric('upcoming_visits', 'Upcoming visits', $this->visitQuery($user)->whereDate('service_date', '>', $today)->count(), 'Later scheduled visits'),
            $this->metric('assigned_clients', 'Assigned clients', $employee === null ? 0 : $employee->clientAssignments()->active()->count(), 'Active client assignments'),
        ];
    }

    /**
     * @return array{key: string, label: string, value: int, hint: string}
     */
    private function metric(string $key, string $label, int $value, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
        ];
    }

    /**
     * @return Builder<ScheduledVisit>
     */
    private function visitQuery(User $user): Builder
    {
        return $this->visitsQuery($user)->open();
    }

    /**
     * @return Builder<ScheduledVisit>
     */
    private function visitsQuery(User $user): Builder
    {
        return ScheduledVisit::query()->visibleTo($user);
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function todayVisits(User $user, CarbonInterface $today): Collection
    {
        return $this->visitQuery($user)
            ->with(['client', 'employee', 'shiftTemplate'])
            ->whereDate('service_date', $today)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function upcomingVisits(User $user, CarbonInterface $today): Collection
    {
        return $this->visitsQuery($user)
            ->scheduled()
            ->with(['client', 'employee', 'shiftTemplate'])
            ->whereDate('service_date', '>', $today)
            ->orderBy('service_date')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeVisit(User $user, ?Employee $employee): ?array
    {
        if (! $user->isDsp() || $employee === null) {
            return null;
        }

        $visit = $this->clockIn->activeVisitFor($employee);

        return $visit === null ? null : DirectoryPresenter::activeVisitSummary($visit);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clockInVisit(User $user, ?Employee $employee): ?array
    {
        if (! $user->isDsp() || $employee === null) {
            return null;
        }

        if ($this->clockIn->activeVisitFor($employee) !== null) {
            return null;
        }

        $visit = $this->clockIn->eligibleScheduledVisitFor($employee);

        return $visit === null ? null : DirectoryPresenter::clockInVisitSummary($visit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedDsps(User $user, ?Employee $employee): array
    {
        if (! $user->isSupervisor()) {
            return [];
        }

        return $this->values($this->dspReportsQuery($employee)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $dsp): array => [
                'id' => $dsp->id,
                'name' => $dsp->full_name,
                'employee_number' => $dsp->employee_number,
                'job_title' => $dsp->job_title,
            ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedClients(User $user, ?Employee $employee): array
    {
        if ($user->isDsp()) {
            if ($employee === null) {
                return [];
            }

            return $this->values($employee->clientAssignments()
                ->active()
                ->with('client')
                ->get()
                ->map(fn (ClientDspAssignment $assignment): array => $this->clientSummary($assignment->client)));
        }

        if (! $user->isSupervisor()) {
            return [];
        }

        return $this->values($this->supervisedClientsQuery($employee)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Client $client): array => $this->clientSummary($client)));
    }

    /**
     * @return list<array{id: string, tone: string, title: string, detail: string}>
     */
    private function attentionItems(User $user, ?Employee $employee): array
    {
        $items = [];

        foreach ($this->credentialAttentionQuery($user, $employee)->with('employee')->limit(8)->get() as $credential) {
            $items[] = [
                'id' => 'credential-'.$credential->id,
                'tone' => $credential->status === CredentialStatus::Expired ? 'danger' : 'warning',
                'title' => $credential->name,
                'detail' => $credential->employee->full_name.' · '.$credential->status->value,
            ];
        }

        foreach ($this->trainingAttentionQuery($user, $employee)->with('employee')->limit(6)->get() as $training) {
            $items[] = [
                'id' => 'training-'.$training->id,
                'tone' => 'warning',
                'title' => $training->title,
                'detail' => $training->employee->full_name.' · '.$training->status->value,
            ];
        }

        foreach ($this->authorizationAttentionQuery($user, $employee)->with('client')->limit(6)->get() as $authorization) {
            $items[] = [
                'id' => 'authorization-'.$authorization->id,
                'tone' => $authorization->status === AuthorizationStatus::Expired ? 'danger' : 'warning',
                'title' => $authorization->service_type.' authorization',
                'detail' => $authorization->client->full_name.' · '.$authorization->status->value,
            ];
        }

        $cancelled = $this->visitsQuery($user)
            ->where('status', ScheduledVisitStatus::Cancelled)
            ->with(['client', 'employee'])
            ->orderByDesc('service_date')
            ->limit(4)
            ->get();

        foreach ($cancelled as $visit) {
            $items[] = [
                'id' => 'cancelled-'.$visit->id,
                'tone' => 'neutral',
                'title' => 'Cancelled visit',
                'detail' => $visit->client->full_name.' · '.$visit->service_date->toFormattedDateString(),
            ];
        }

        return array_slice($items, 0, 10);
    }

    /**
     * @return list<array{id: string, title: string, detail: string, occurred_on: string}>
     */
    private function activity(User $user): array
    {
        return $this->values($this->visitsQuery($user)
            ->with(['client', 'employee'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (ScheduledVisit $visit): array => [
                'id' => 'visit-'.$visit->id,
                'title' => $visit->service_type,
                'detail' => $visit->client->full_name.' with '.$visit->employee->full_name,
                'occurred_on' => $visit->service_date->toDateString(),
            ]));
    }

    /**
     * @param  Collection<int, ScheduledVisit>  $visits
     * @return list<array<string, mixed>>
     */
    private function serializeVisits(Collection $visits): array
    {
        return $this->values($visits->map(fn (ScheduledVisit $visit): array => [
            'id' => $visit->id,
            'service_date' => $visit->service_date->toDateString(),
            'service_type' => $visit->service_type,
            'status' => $visit->status->value,
            'time_label' => $this->visitTimeLabel($visit),
            'shift_name' => $visit->shiftTemplate?->name,
            'client' => $this->clientSummary($visit->client),
            'employee' => [
                'id' => $visit->employee->id,
                'name' => $visit->employee->full_name,
            ],
        ]));
    }

    private function visitTimeLabel(ScheduledVisit $visit): string
    {
        $start = $visit->startsAtOn();
        $end = $visit->endsAtOn();
        $label = $start->format('g:i A').' – '.$end->format('g:i A');

        if ($visit->spansOvernight()) {
            return $label.' next day';
        }

        return $label;
    }

    /**
     * @return array{id: int, name: string, client_number: string}
     */
    private function clientSummary(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->full_name,
            'client_number' => $client->client_number,
        ];
    }

    /**
     * @return Builder<Employee>
     */
    private function dspReportsQuery(?Employee $employee): Builder
    {
        if ($employee === null) {
            return Employee::query()->whereRaw('1 = 0');
        }

        return Employee::query()
            ->where('supervisor_id', $employee->id)
            ->where('job_type', JobType::Dsp)
            ->where('employment_status', EmploymentStatus::Active);
    }

    /**
     * @return Builder<Client>
     */
    private function supervisedClientsQuery(?Employee $employee): Builder
    {
        if ($employee === null) {
            return Client::query()->whereRaw('1 = 0');
        }

        return Client::query()
            ->where('supervisor_id', $employee->id)
            ->where('status', ClientStatus::Active);
    }

    /**
     * @return Builder<EmployeeCredential>
     */
    private function credentialAttentionQuery(User $user, ?Employee $employee): Builder
    {
        $query = EmployeeCredential::query()
            ->whereHas('employee', function (Builder $builder) use ($user, $employee): void {
                $builder->where('employment_status', EmploymentStatus::Active);

                if ($user->isSupervisor()) {
                    $ids = $this->dspReportsQuery($employee)->pluck('id');
                    $builder->whereIn('id', $ids);
                } elseif ($user->isDsp()) {
                    $builder->where('id', $employee === null ? 0 : $employee->id);
                }
            })
            ->where(function (Builder $builder): void {
                $builder->whereIn('status', [
                    CredentialStatus::Pending,
                    CredentialStatus::Expired,
                    CredentialStatus::Revoked,
                ])->orWhere(function (Builder $inner): void {
                    $inner->where('status', CredentialStatus::Active)
                        ->whereNotNull('expires_on')
                        ->whereDate('expires_on', '<=', now()->addDays(30)->toDateString());
                });
            })
            ->orderBy('expires_on');

        return $query;
    }

    /**
     * @return Builder<EmployeeTraining>
     */
    private function trainingAttentionQuery(User $user, ?Employee $employee): Builder
    {
        return EmployeeTraining::query()
            ->whereHas('employee', function (Builder $builder) use ($user, $employee): void {
                $builder->where('employment_status', EmploymentStatus::Active);

                if ($user->isSupervisor()) {
                    $ids = $this->dspReportsQuery($employee)->pluck('id');
                    $builder->whereIn('id', $ids);
                } elseif ($user->isDsp()) {
                    $builder->where('id', $employee === null ? 0 : $employee->id);
                }
            })
            ->where(function (Builder $builder): void {
                $builder->whereIn('status', [
                    TrainingStatus::InProgress,
                    TrainingStatus::Expired,
                ])->orWhere(function (Builder $inner): void {
                    $inner->where('status', TrainingStatus::Completed)
                        ->whereNotNull('expires_on')
                        ->whereDate('expires_on', '<', now()->toDateString());
                });
            });
    }

    /**
     * @return Builder<ClientAuthorization>
     */
    private function authorizationAttentionQuery(User $user, ?Employee $employee): Builder
    {
        return ClientAuthorization::query()
            ->whereHas('client', function (Builder $builder) use ($user, $employee): void {
                if ($user->isSupervisor()) {
                    $ids = $this->supervisedClientsQuery($employee)->pluck('id');
                    $builder->whereIn('id', $ids);
                } elseif ($user->isDsp()) {
                    $clientIds = $employee?->clientAssignments()->active()->pluck('client_id') ?? collect();
                    $builder->whereIn('id', $clientIds);
                }
            })
            ->whereIn('status', [
                AuthorizationStatus::Pending,
                AuthorizationStatus::Expired,
                AuthorizationStatus::Exhausted,
            ]);
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private function values(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }
}
