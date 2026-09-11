<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ComplianceDateStatus;
use App\Enums\ReportType;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use App\Support\DirectoryPresenter;
use App\Support\OperationalReport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceService $attendance,
        private AttendanceStatusService $statuses,
        private ComplianceService $compliance,
    ) {}

    /**
     * @return list<array{key: string, title: string, description: string}>
     */
    public function catalog(): array
    {
        return array_map(
            fn (ReportType $type): array => [
                'key' => $type->value,
                'title' => $type->title(),
                'description' => $type->description(),
            ],
            ReportType::cases(),
        );
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function page(User $user, ReportType $type, array $filters, int $page, string $url, array $query): array
    {
        $built = $this->build($user, $type, $filters);

        return [
            'report' => [
                'key' => $type->value,
                'title' => $type->title(),
                'description' => $type->description(),
            ],
            'columns' => $built['columns'],
            'rows' => $this->paginate($built['rows'], $page, $url, $query),
            'summary' => $built['summary'],
            'filters' => $filters,
            'filter_visibility' => $this->filterVisibility($type),
            'clients' => $type->usesClientFilter() ? DirectoryPresenter::clientFilterOptions($user) : [],
            'dsps' => DirectoryPresenter::dspFilterOptions($user),
            'supervisors' => $user->isAdmin() ? DirectoryPresenter::supervisorOptions() : [],
            'statuses' => $this->statusOptions($type),
            'timezone' => $this->settings->timezone(),
            'can' => [
                'export' => $type === ReportType::PayrollHours
                    ? $user->can('exportPayroll', OperationalReport::class)
                    : $user->can('export', OperationalReport::class),
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     */
    public function download(User $user, ReportType $type, array $filters): StreamedResponse
    {
        $built = $this->build($user, $type, $filters);
        $from = $filters['from'] !== '' ? $filters['from'] : 'all';
        $to = $filters['to'] !== '' ? $filters['to'] : 'all';
        $filename = $type->value.'-'.$from.'-'.$to.'.csv';
        $headers = array_map(fn (array $column): string => $column['label'], $built['columns']);
        $keys = array_map(fn (array $column): string => $column['key'], $built['columns']);

        return response()->streamDownload(function () use ($headers, $keys, $built): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($built['rows'] as $row) {
                $line = [];

                foreach ($keys as $key) {
                    $value = $row[$key] ?? '';
                    $line[] = is_scalar($value) ? (string) $value : '';
                }

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: array<string, mixed>|null}
     */
    private function build(User $user, ReportType $type, array $filters): array
    {
        return match ($type) {
            ReportType::EmployeeAttendance => $this->employeeAttendance($user, $filters),
            ReportType::ClientVisits => $this->clientVisits($user, $filters),
            ReportType::LateMissedVisits => $this->lateMissed($user, $filters),
            ReportType::TaskCompletion => $this->taskCompletion($user, $filters),
            ReportType::CredentialTrainingExpiration => $this->credentialExpiration($user, $filters),
            ReportType::Compliance => $this->compliance($user, $filters),
            ReportType::Exceptions => $this->exceptions($user, $filters),
            ReportType::PayrollHours => $this->payrollHours($user, $filters),
        };
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function employeeAttendance(User $user, array $filters): array
    {
        $rows = $this->attendance->records($user, $filters)
            ->map(fn (array $row): array => $this->attendanceRow($row))
            ->all();

        return [
            'columns' => $this->attendanceColumns(),
            'rows' => array_values($rows),
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function lateMissed(User $user, array $filters): array
    {
        $status = AttendanceStatus::tryFrom($filters['status']);
        $records = $this->attendance->records($user, [...$filters, 'status' => '']);
        $allowed = [AttendanceStatus::Late->value, AttendanceStatus::Missed->value];

        $rows = [];

        foreach ($records as $row) {
            if ($status !== null && $row['status'] !== $status->value) {
                continue;
            }

            if ($status === null && ! in_array($row['status'], $allowed, true)) {
                continue;
            }

            $rows[] = $this->attendanceRow($row);
        }

        return [
            'columns' => $this->attendanceColumns(),
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function clientVisits(User $user, array $filters): array
    {
        $status = VisitStatus::tryFrom($filters['status']);
        $rows = [];

        $visits = Visit::query()
            ->visibleTo($user)
            ->with(['client', 'employee.supervisor', 'scheduledVisit.supervisor', 'scheduledVisit.employee.supervisor'])
            ->whereIn('scheduled_visit_id', $this->attendance->query($user, $filters)->select('id'))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('clocked_in_at')
            ->orderByDesc('id')
            ->get();

        foreach ($visits as $visit) {
            $scheduled = $visit->scheduledVisit;
            $rows[] = [
                'service_date' => $scheduled->service_date->toDateString(),
                'client' => $visit->client->full_name,
                'employee' => $visit->employee->full_name,
                'supervisor' => $this->supervisorName($scheduled),
                'service_type' => $visit->service_type,
                'status' => $visit->status->value,
                'status_label' => $visit->status->value === VisitStatus::InProgress->value ? 'In Progress' : 'Completed',
                'clock_in' => $this->stamp($visit->clocked_in_at),
                'clock_out' => $this->stamp($visit->clocked_out_at),
            ];
        }

        return [
            'columns' => [
                ['key' => 'service_date', 'label' => 'Service Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'supervisor', 'label' => 'Supervisor'],
                ['key' => 'service_type', 'label' => 'Service'],
                ['key' => 'status_label', 'label' => 'Status'],
                ['key' => 'clock_in', 'label' => 'Clock In'],
                ['key' => 'clock_out', 'label' => 'Clock Out'],
            ],
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function taskCompletion(User $user, array $filters): array
    {
        $status = VisitTaskStatus::tryFrom($filters['status']);
        $rows = [];

        $tasks = VisitTask::query()
            ->with(['skipReason', 'visit.client', 'visit.employee', 'visit.scheduledVisit'])
            ->whereIn(
                'visit_id',
                Visit::query()
                    ->visibleTo($user)
                    ->whereIn('scheduled_visit_id', $this->attendance->query($user, $filters)->select('id'))
                    ->select('id'),
            )
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        foreach ($tasks as $task) {
            $visit = $task->visit;
            $scheduled = $visit->scheduledVisit;
            $rows[] = [
                'service_date' => $scheduled->service_date->toDateString(),
                'client' => $visit->client->full_name,
                'employee' => $visit->employee->full_name,
                'task' => $task->title,
                'required' => $task->is_required ? 'Required' : 'Optional',
                'status' => $task->status->value,
                'status_label' => match ($task->status) {
                    VisitTaskStatus::Completed => 'Completed',
                    VisitTaskStatus::Skipped => 'Skipped',
                    VisitTaskStatus::Pending => 'Pending',
                },
                'skip_reason' => $task->skipReason?->name,
            ];
        }

        return [
            'columns' => [
                ['key' => 'service_date', 'label' => 'Service Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'task', 'label' => 'Task'],
                ['key' => 'required', 'label' => 'Required'],
                ['key' => 'status_label', 'label' => 'Status'],
                ['key' => 'skip_reason', 'label' => 'Skip Reason'],
            ],
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function credentialExpiration(User $user, array $filters): array
    {
        $status = ComplianceDateStatus::tryFrom($filters['status']);
        $attention = [
            ComplianceDateStatus::Expired->value,
            ComplianceDateStatus::ExpiringSoon->value,
        ];

        $rows = [];

        foreach ($this->complianceItems($user, $filters) as $item) {
            if ($status !== null && $item['status'] !== $status->value) {
                continue;
            }

            if ($status === null && ! in_array($item['status'], $attention, true)) {
                continue;
            }

            if (! $this->expiresWithin($item['expires_on'] ?? null, $filters)) {
                continue;
            }

            $rows[] = [
                'employee_number' => $item['employee_number'],
                'employee' => $item['employee_name'],
                'kind' => $item['kind'] === 'credential' ? 'Credential' : 'Training',
                'title' => $item['title'],
                'expires_on' => $item['expires_on'],
                'status' => $item['status'],
                'status_label' => $item['status_label'],
            ];
        }

        return [
            'columns' => [
                ['key' => 'employee_number', 'label' => 'Employee ID'],
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'kind', 'label' => 'Type'],
                ['key' => 'title', 'label' => 'Record'],
                ['key' => 'expires_on', 'label' => 'Expires On'],
                ['key' => 'status_label', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function compliance(User $user, array $filters): array
    {
        $status = ComplianceDateStatus::tryFrom($filters['status']);
        $rows = [];

        foreach ($this->complianceItems($user, $filters) as $item) {
            if ($status !== null && $item['status'] !== $status->value) {
                continue;
            }

            $rows[] = [
                'employee_number' => $item['employee_number'],
                'employee' => $item['employee_name'],
                'kind' => $item['kind'] === 'credential' ? 'Credential' : 'Training',
                'title' => $item['title'],
                'expires_on' => $item['expires_on'],
                'status' => $item['status'],
                'status_label' => $item['status_label'],
            ];
        }

        return [
            'columns' => [
                ['key' => 'employee_number', 'label' => 'Employee ID'],
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'kind', 'label' => 'Type'],
                ['key' => 'title', 'label' => 'Record'],
                ['key' => 'expires_on', 'label' => 'Expires On'],
                ['key' => 'status_label', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: null}
     */
    private function exceptions(User $user, array $filters): array
    {
        $status = VisitExceptionStatus::tryFrom($filters['status']);
        $rows = [];

        $exceptions = VisitException::query()
            ->visibleTo($user)
            ->with(['visit.client', 'visit.employee', 'visit.scheduledVisit', 'visitTask'])
            ->whereIn(
                'visit_id',
                Visit::query()
                    ->whereIn('scheduled_visit_id', $this->attendance->query($user, $filters)->select('id'))
                    ->select('id'),
            )
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        foreach ($exceptions as $exception) {
            $visit = $exception->visit;
            $scheduled = $visit->scheduledVisit;
            $rows[] = [
                'service_date' => $scheduled->service_date->toDateString(),
                'client' => $visit->client->full_name,
                'employee' => $visit->employee->full_name,
                'type' => $exception->type->label(),
                'status' => $exception->status->value,
                'status_label' => $exception->status->label(),
                'message' => $exception->message,
                'task' => $exception->visitTask?->title,
            ];
        }

        return [
            'columns' => [
                ['key' => 'service_date', 'label' => 'Service Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'employee', 'label' => 'Employee'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'status_label', 'label' => 'Status'],
                ['key' => 'task', 'label' => 'Task'],
                ['key' => 'message', 'label' => 'Message'],
            ],
            'rows' => $rows,
            'summary' => null,
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function payrollHours(User $user, array $filters): array
    {
        $scheduledVisits = $this->attendance->query($user, $filters)
            ->with(['employee', 'visit', 'attendanceCorrections'])
            ->orderBy('employee_id')
            ->get();

        /** @var array<int, array{employee_id: int, employee_number: string, employee_name: string, completed_visit_count: int, worked_minutes: int}> $byEmployee */
        $byEmployee = [];

        foreach ($scheduledVisits as $scheduled) {
            $visit = $scheduled->visit;

            if ($visit === null || $visit->status !== VisitStatus::Completed) {
                continue;
            }

            $employee = $scheduled->employee;
            $id = $employee->id;
            $byEmployee[$id] ??= [
                'employee_id' => $id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->full_name,
                'completed_visit_count' => 0,
                'worked_minutes' => 0,
            ];
            $byEmployee[$id]['completed_visit_count']++;

            $minutes = $this->statuses->workedMinutes($scheduled);

            if ($minutes !== null) {
                $byEmployee[$id]['worked_minutes'] += $minutes;
            }
        }

        $rows = [];
        $totalMinutes = 0;
        $totalVisits = 0;

        foreach ($byEmployee as $entry) {
            $totalMinutes += $entry['worked_minutes'];
            $totalVisits += $entry['completed_visit_count'];
            $rows[] = [
                'employee_number' => $entry['employee_number'],
                'employee_name' => $entry['employee_name'],
                'period_start' => $filters['from'],
                'period_end' => $filters['to'],
                'completed_visit_count' => $entry['completed_visit_count'],
                'worked_hours' => $this->hoursLabel($entry['worked_minutes']),
            ];
        }

        usort($rows, fn (array $left, array $right): int => $left['employee_name'] <=> $right['employee_name']);

        return [
            'columns' => [
                ['key' => 'employee_number', 'label' => 'Employee ID'],
                ['key' => 'employee_name', 'label' => 'Employee Name'],
                ['key' => 'period_start', 'label' => 'Period Start'],
                ['key' => 'period_end', 'label' => 'Period End'],
                ['key' => 'completed_visit_count', 'label' => 'Completed Visit Count'],
                ['key' => 'worked_hours', 'label' => 'Worked Hours'],
            ],
            'rows' => $rows,
            'summary' => [
                'completed_visit_count' => $totalVisits,
                'worked_hours' => $this->hoursLabel($totalMinutes),
                'employee_count' => count($rows),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function attendanceColumns(): array
    {
        return [
            ['key' => 'service_date', 'label' => 'Service Date'],
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'client', 'label' => 'Client'],
            ['key' => 'supervisor', 'label' => 'Supervisor'],
            ['key' => 'scheduled_time', 'label' => 'Scheduled'],
            ['key' => 'effective_clock_in', 'label' => 'Effective Clock In'],
            ['key' => 'effective_clock_out', 'label' => 'Effective Clock Out'],
            ['key' => 'worked_duration', 'label' => 'Worked'],
            ['key' => 'status_label', 'label' => 'Status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attendanceRow(array $row): array
    {
        return [
            'service_date' => $row['service_date'],
            'employee' => $row['employee']['name'],
            'client' => $row['client']['name'],
            'supervisor' => $row['supervisor_name'],
            'scheduled_time' => $row['scheduled_time'],
            'effective_clock_in' => $row['effective_clock_in'],
            'effective_clock_out' => $row['effective_clock_out'],
            'worked_duration' => $row['worked_duration'],
            'status' => $row['status'],
            'status_label' => $row['status_label'],
        ];
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function complianceItems(User $user, array $filters): array
    {
        $items = $this->compliance->records($user);

        if ($filters['employee_id'] !== '' && ctype_digit($filters['employee_id'])) {
            $employeeId = (int) $filters['employee_id'];
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $item['employee_id'] === $employeeId,
            ));
        }

        if ($user->isAdmin() && $filters['supervisor_id'] !== '' && ctype_digit($filters['supervisor_id'])) {
            $supervisorId = (int) $filters['supervisor_id'];
            $ids = Employee::query()
                ->where('supervisor_id', $supervisorId)
                ->pluck('id')
                ->all();
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => in_array($item['employee_id'], $ids, true),
            ));
        }

        return $items;
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     */
    private function expiresWithin(?string $expiresOn, array $filters): bool
    {
        if ($filters['from'] === '' && $filters['to'] === '') {
            return true;
        }

        if ($expiresOn === null) {
            return false;
        }

        if ($filters['from'] !== '' && $expiresOn < $filters['from']) {
            return false;
        }

        if ($filters['to'] !== '' && $expiresOn > $filters['to']) {
            return false;
        }

        return true;
    }

    /**
     * @return array{dates: bool, employee: bool, client: bool, supervisor: bool, status: bool}
     */
    private function filterVisibility(ReportType $type): array
    {
        return [
            'dates' => $type->usesDateRange(),
            'employee' => true,
            'client' => $type->usesClientFilter(),
            'supervisor' => true,
            'status' => $type->usesStatusFilter(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(ReportType $type): array
    {
        return match ($type) {
            ReportType::EmployeeAttendance => $this->enumOptions(AttendanceStatus::cases()),
            ReportType::LateMissedVisits => [
                ['value' => AttendanceStatus::Late->value, 'label' => AttendanceStatus::Late->label()],
                ['value' => AttendanceStatus::Missed->value, 'label' => AttendanceStatus::Missed->label()],
            ],
            ReportType::ClientVisits => [
                ['value' => VisitStatus::InProgress->value, 'label' => 'In Progress'],
                ['value' => VisitStatus::Completed->value, 'label' => 'Completed'],
            ],
            ReportType::TaskCompletion => [
                ['value' => VisitTaskStatus::Pending->value, 'label' => 'Pending'],
                ['value' => VisitTaskStatus::Completed->value, 'label' => 'Completed'],
                ['value' => VisitTaskStatus::Skipped->value, 'label' => 'Skipped'],
            ],
            ReportType::CredentialTrainingExpiration, ReportType::Compliance => $this->enumOptions(ComplianceDateStatus::cases()),
            ReportType::Exceptions => $this->enumOptions(VisitExceptionStatus::cases()),
            ReportType::PayrollHours => [],
        };
    }

    /**
     * @param  list<AttendanceStatus|ComplianceDateStatus|VisitExceptionStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $options;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $query
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, from: int|null, to: int|null, total: int}, links: array{prev: string|null, next: string|null}}
     */
    private function paginate(array $rows, int $page, string $url, array $query, int $perPage = 50): array
    {
        $page = max(1, $page);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);
        $queryWithoutPage = $query;
        unset($queryWithoutPage['page']);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total === 0 ? null : $offset + 1,
                'to' => $total === 0 ? null : min($offset + $perPage, $total),
                'total' => $total,
            ],
            'links' => [
                'prev' => $page > 1 ? $url.'?'.http_build_query([...$queryWithoutPage, 'page' => $page - 1]) : null,
                'next' => $page < $lastPage ? $url.'?'.http_build_query([...$queryWithoutPage, 'page' => $page + 1]) : null,
            ],
        ];
    }

    private function supervisorName(ScheduledVisit $scheduledVisit): ?string
    {
        $ofRecord = $scheduledVisit->relationLoaded('supervisor') ? $scheduledVisit->getRelation('supervisor') : $scheduledVisit->supervisor;

        if ($ofRecord instanceof Employee) {
            return $ofRecord->full_name;
        }

        $assigned = $scheduledVisit->employee->supervisor ?? null;

        return $assigned instanceof Employee ? $assigned->full_name : null;
    }

    private function stamp(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $this->settings->formatDateTime($value);
    }

    private function hoursLabel(int $minutes): string
    {
        return number_format($minutes / 60, 2, '.', '');
    }
}
