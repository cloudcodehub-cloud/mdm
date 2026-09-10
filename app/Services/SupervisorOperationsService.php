<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use App\Support\DirectoryPresenter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupervisorOperationsService
{
    public function __construct(
        private SettingsService $settings,
        private VisitOperationsStatus $operationsStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        if ($user->isAdmin()) {
            return $this->board(
                $this->scheduledVisitQuery($user),
                $this->visitQuery($user),
                $this->exceptionQuery($user),
                Employee::query()
                    ->where('job_type', JobType::Dsp)
                    ->where('employment_status', EmploymentStatus::Active),
                Client::query()->where('status', ClientStatus::Active),
            );
        }

        return $this->board(
            $this->scheduledVisitQuery($user),
            $this->visitQuery($user),
            $this->exceptionQuery($user),
            $this->dspReportsQuery($user->employee),
            $this->supervisedClientsQuery($user->employee),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forSupervisor(Employee $supervisor): array
    {
        $user = $supervisor->user;

        if ($user !== null && $user->isSupervisor()) {
            return $this->forUser($user);
        }

        return $this->board(
            $this->caseloadScheduledVisitQuery($supervisor),
            $this->caseloadVisitQuery($supervisor),
            $this->caseloadExceptionQuery($supervisor),
            $this->dspReportsQuery($supervisor),
            $this->supervisedClientsQuery($supervisor),
        );
    }

    /**
     * @param  Builder<ScheduledVisit>  $scheduledQuery
     * @param  Builder<Visit>  $visitQuery
     * @param  Builder<VisitException>  $exceptionQuery
     * @param  Builder<Employee>  $dspQuery
     * @param  Builder<Client>  $clientQuery
     * @return array<string, mixed>
     */
    private function board(
        Builder $scheduledQuery,
        Builder $visitQuery,
        Builder $exceptionQuery,
        Builder $dspQuery,
        Builder $clientQuery,
    ): array {
        $today = $this->settings->today();
        $now = $this->settings->now();

        $todayVisits = (clone $scheduledQuery)
            ->with([
                'client',
                'employee',
                'shiftTemplate',
                'visit.tasks.skipReason',
                'visit.exceptions',
            ])
            ->whereDate('service_date', $today)
            ->where('status', '!=', ScheduledVisitStatus::Cancelled)
            ->orderBy('id')
            ->get();

        $activeVisits = (clone $visitQuery)
            ->with([
                'client',
                'employee',
                'scheduledVisit.shiftTemplate',
                'tasks.skipReason',
                'exceptions',
            ])
            ->inProgress()
            ->orderBy('clocked_in_at')
            ->get();

        $completedVisits = (clone $visitQuery)
            ->with([
                'client',
                'employee',
                'scheduledVisit.shiftTemplate',
                'tasks.skipReason',
                'exceptions',
            ])
            ->where('status', VisitStatus::Completed)
            ->whereHas('scheduledVisit', fn (Builder $query): Builder => $query->whereDate('service_date', $today))
            ->orderByDesc('clocked_out_at')
            ->get();

        $skippedTasks = VisitTask::query()
            ->with(['skipReason', 'visit.client', 'visit.employee'])
            ->where('status', VisitTaskStatus::Skipped)
            ->whereIn('visit_id', (clone $visitQuery)->select('id'))
            ->whereHas('visit.scheduledVisit', fn (Builder $query): Builder => $query->whereDate('service_date', $today))
            ->orderByDesc('skipped_at')
            ->orderBy('id')
            ->get();

        $handovers = (clone $visitQuery)
            ->with(['client', 'employee', 'scheduledVisit'])
            ->whereNotNull('handover_note')
            ->whereHas('scheduledVisit', fn (Builder $query): Builder => $query->whereDate('service_date', $today))
            ->orderByDesc('clocked_out_at')
            ->orderByDesc('id')
            ->get();

        $exceptions = (clone $exceptionQuery)
            ->with(['visit.client', 'visit.employee', 'visitTask', 'reviewedBy', 'resolvedBy'])
            ->orderByDesc('id')
            ->get();

        $openExceptions = $exceptions->filter(fn (VisitException $exception): bool => $exception->isOpen());

        $assignedDsps = (clone $dspQuery)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $assignedClients = (clone $clientQuery)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return [
            'today' => $today,
            'timezone' => $this->settings->timezone(),
            'timezone_label' => $this->settings->timezoneLabel($this->settings->timezone()),
            'metrics' => [
                $this->metric('assigned_dsps', 'Assigned DSPs', $assignedDsps->count(), 'Active DSP reports on this caseload'),
                $this->metric('assigned_clients', 'Assigned clients', $assignedClients->count(), 'Active clients on this caseload'),
                $this->metric('visits_today', "Today's scheduled visits", $todayVisits->count(), 'Service date in the operational timezone'),
                $this->metric('active_visits', 'Active visits', $activeVisits->count(), 'Currently clocked-in visits'),
                $this->metric('open_exceptions', 'Open exceptions', $openExceptions->count(), 'Exceptions awaiting review or resolution'),
            ],
            'assigned_dsps' => $this->people($assignedDsps),
            'assigned_clients' => $this->clients($assignedClients),
            'today_visits' => $this->boardScheduledVisits($todayVisits, $now),
            'active_visits' => $this->boardVisits($activeVisits, $now),
            'completed_visits' => $this->boardVisits($completedVisits, $now),
            'skipped_tasks' => $this->skippedTasks($skippedTasks),
            'handover_notes' => $this->handovers($handovers),
            'exceptions' => [
                'open' => DirectoryPresenter::visitExceptions($openExceptions),
                'gps' => DirectoryPresenter::visitExceptions($this->exceptionsOfType($openExceptions, VisitExceptionType::GpsUnavailable)),
                'client_refusals' => DirectoryPresenter::visitExceptions($this->exceptionsOfType($openExceptions, VisitExceptionType::ClientRefusal)),
                'critical_skips' => DirectoryPresenter::visitExceptions($this->exceptionsOfType($openExceptions, VisitExceptionType::CriticalTaskSkipped)),
                'unfinished' => DirectoryPresenter::visitExceptions($this->exceptionsOfType($openExceptions, VisitExceptionType::OtherVisitException)),
            ],
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
     * @param  Collection<int, ScheduledVisit>  $visits
     * @return list<array<string, mixed>>
     */
    private function boardScheduledVisits(Collection $visits, CarbonInterface $now): array
    {
        return $this->values($visits->map(fn (ScheduledVisit $visit): array => $this->serializeScheduled($visit, $now)));
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function boardVisits(Collection $visits, CarbonInterface $now): array
    {
        return $this->values($visits->map(function (Visit $visit) use ($now): array {
            $visit->scheduledVisit->setRelation('visit', $visit);

            return $this->serializeScheduled($visit->scheduledVisit, $now);
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScheduled(ScheduledVisit $scheduled, CarbonInterface $now): array
    {
        $visit = $scheduled->visit;
        $status = $this->operationsStatus->forScheduledVisit($scheduled, $now);
        $tasks = $visit === null ? collect() : $visit->tasks;
        $completed = $tasks->where('status', VisitTaskStatus::Completed)->count();
        $skipped = $tasks->where('status', VisitTaskStatus::Skipped)->count();
        $pending = $tasks->where('status', VisitTaskStatus::Pending)->count();

        return [
            'scheduled_visit_id' => $scheduled->id,
            'visit_id' => $visit?->id,
            'service_type' => $scheduled->service_type,
            'service_date' => $scheduled->service_date->toDateString(),
            'time_label' => DirectoryPresenter::visitTimeLabel($scheduled),
            'scheduled_status' => $scheduled->status->value,
            'visit_status' => $visit?->status->value,
            'operational_status' => $status['key'],
            'operational_status_label' => $status['label'],
            'clocked_in_at_label' => $visit === null ? null : $this->settings->formatTime($visit->clocked_in_at),
            'clocked_out_at_label' => $visit?->clocked_out_at === null
                ? null
                : $this->settings->formatTime($visit->clocked_out_at),
            'location_status' => $visit?->clock_in_location_status->value,
            'location_status_label' => $visit === null ? null : DirectoryPresenter::locationStatusLabel($visit),
            'handover_note' => $visit?->handover_note,
            'visit_notes' => $visit?->visit_notes,
            'open_exception_count' => $visit === null
                ? 0
                : $visit->exceptions
                    ->filter(fn (VisitException $exception): bool => $exception->status === VisitExceptionStatus::Open)
                    ->count(),
            'task_summary' => [
                'total' => $tasks->count(),
                'completed' => $completed,
                'skipped' => $skipped,
                'pending' => $pending,
            ],
            'client' => [
                'id' => $scheduled->client->id,
                'name' => $scheduled->client->full_name,
                'client_number' => $scheduled->client->client_number,
            ],
            'employee' => [
                'id' => $scheduled->employee->id,
                'name' => $scheduled->employee->full_name,
                'employee_number' => $scheduled->employee->employee_number,
            ],
        ];
    }

    /**
     * @param  Collection<int, VisitTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function skippedTasks(Collection $tasks): array
    {
        return $this->values($tasks->map(fn (VisitTask $task): array => [
            'id' => $task->id,
            'visit_id' => $task->visit_id,
            'title' => $task->title,
            'is_required' => $task->is_required,
            'skip_reason_name' => $task->skipReason?->name,
            'skip_comment' => $task->skip_comment,
            'client_name' => $task->visit->client->full_name,
            'dsp_name' => $task->visit->employee->full_name,
        ]));
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array<string, mixed>>
     */
    private function handovers(Collection $visits): array
    {
        return $this->values($visits->map(fn (Visit $visit): array => [
            'id' => $visit->id,
            'visit_id' => $visit->id,
            'handover_note' => $visit->handover_note,
            'client_name' => $visit->client->full_name,
            'dsp_name' => $visit->employee->full_name,
            'service_type' => $visit->service_type,
        ]));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function people(Collection $employees): array
    {
        return $this->values($employees->map(fn (Employee $employee): array => [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'job_title' => $employee->job_title,
        ]));
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @return list<array<string, mixed>>
     */
    private function clients(Collection $clients): array
    {
        return $this->values($clients->map(fn (Client $client): array => [
            'id' => $client->id,
            'name' => $client->full_name,
            'client_number' => $client->client_number,
        ]));
    }

    /**
     * @param  Collection<int, VisitException>  $exceptions
     * @return Collection<int, VisitException>
     */
    private function exceptionsOfType(Collection $exceptions, VisitExceptionType $type): Collection
    {
        return $exceptions->filter(fn (VisitException $exception): bool => $exception->type === $type)->values();
    }

    /**
     * @return Builder<ScheduledVisit>
     */
    private function scheduledVisitQuery(User $user): Builder
    {
        return ScheduledVisit::query()->visibleTo($user);
    }

    /**
     * @return Builder<Visit>
     */
    private function visitQuery(User $user): Builder
    {
        return Visit::query()->visibleTo($user);
    }

    /**
     * @return Builder<VisitException>
     */
    private function exceptionQuery(User $user): Builder
    {
        return VisitException::query()->visibleTo($user);
    }

    /**
     * @return Builder<ScheduledVisit>
     */
    private function caseloadScheduledVisitQuery(Employee $supervisor): Builder
    {
        $dspIds = $this->dspReportsQuery($supervisor)->pluck('id');
        $clientIds = $this->supervisedClientsQuery($supervisor)->pluck('id');

        return ScheduledVisit::query()->where(function (Builder $builder) use ($supervisor, $dspIds, $clientIds): void {
            $builder->where('supervisor_id', $supervisor->id)
                ->orWhereIn('employee_id', $dspIds)
                ->orWhereIn('client_id', $clientIds);
        });
    }

    /**
     * @return Builder<Visit>
     */
    private function caseloadVisitQuery(Employee $supervisor): Builder
    {
        return Visit::query()->whereIn(
            'scheduled_visit_id',
            $this->caseloadScheduledVisitQuery($supervisor)->select('id'),
        );
    }

    /**
     * @return Builder<VisitException>
     */
    private function caseloadExceptionQuery(Employee $supervisor): Builder
    {
        return VisitException::query()->whereIn(
            'visit_id',
            $this->caseloadVisitQuery($supervisor)->select('id'),
        );
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
