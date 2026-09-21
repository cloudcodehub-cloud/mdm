<?php

namespace App\Services\Documents;

use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\AttendanceService;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use App\Services\VisitCarePreviewService;
use App\Support\DirectoryPresenter;
use App\Support\Documents\DocumentDefinition;
use App\Support\Documents\DocumentFilename;
use App\Support\OperationalReport;
use Illuminate\Support\Str;

class MdmDocumentFactory
{
    public function __construct(
        private DocumentContextFactory $contexts,
        private SettingsService $settings,
        private VisitCarePreviewService $carePreview,
        private AttendanceService $attendance,
        private AttendanceStatusService $attendanceStatuses,
    ) {}

    public function visitHandout(ScheduledVisit $visit, User $user): DocumentDefinition
    {
        $visit->loadMissing([
            'client',
            'employee',
            'supervisor',
            'shiftTemplate',
            'oneOffTasks',
            'careServices',
            'taskOverrides',
        ]);

        $serviceIds = [];

        foreach ($visit->careServices as $service) {
            $serviceIds[] = $service->id;
        }
        $preview = $this->carePreview->forClientOnDate(
            $visit->client,
            $visit->service_date->toDateString(),
            $serviceIds,
            $visit,
        );

        $tasks = [];

        foreach ($preview['tasks'] as $task) {
            if (! ($task['included'] ?? false)) {
                continue;
            }

            $tasks[] = [
                'title' => (string) $task['title'],
                'instructions' => $task['instructions'] ?? null,
                'is_required' => (bool) ($task['is_required'] ?? false),
                'is_critical' => (bool) ($task['is_critical'] ?? false),
                'source_label' => 'Care plan · this visit',
            ];
        }

        foreach ($visit->oneOffTasks as $oneOff) {
            $tasks[] = [
                'title' => $oneOff->title,
                'instructions' => $oneOff->instructions,
                'is_required' => $oneOff->is_required,
                'is_critical' => false,
                'source_label' => 'Visit-only',
            ];
        }

        $context = $this->contexts->make($user);
        $clientNumber = $visit->client->client_number;
        $serviceDate = $visit->service_date->toDateString();

        return new DocumentDefinition(
            title: 'Client Visit & Task Handout',
            view: 'documents.visit-handout',
            filename: DocumentFilename::make(
                $context->agencySlug,
                'Visit-Handout',
                $clientNumber,
                $serviceDate,
            ),
            subtitle: $visit->client->full_name,
            reference: $clientNumber.' · '.$this->settings->formatCalendarDate($visit->service_date),
            data: [
                'client_name' => $visit->client->full_name,
                'client_number' => $clientNumber,
                'service' => $visit->service_type,
                'services' => $visit->careServices->pluck('name')->filter()->values()->all(),
                'visit_date' => $this->settings->formatCalendarDate($visit->service_date),
                'scheduled_window' => DirectoryPresenter::visitTimeLabel($visit),
                'dsp_name' => $visit->employee->full_name,
                'supervisor_name' => $visit->supervisor?->full_name,
                'instructions' => $visit->notes,
                'tasks' => $tasks,
            ],
            context: $context,
        );
    }

    public function completedVisit(Visit $visit, User $user): DocumentDefinition
    {
        abort_unless($visit->status === VisitStatus::Completed, 404);

        $visit->loadMissing([
            'client.supervisor',
            'employee',
            'scheduledVisit.shiftTemplate',
            'scheduledVisit.supervisor',
            'scheduledVisit.attendanceCorrections',
            'tasks.skipReason',
            'exceptions.visitTask',
            'exceptions.reviewedBy',
            'exceptions.resolvedBy',
        ]);

        $scheduled = $visit->scheduledVisit;
        $effective = $this->attendanceStatuses->effectiveTimes($scheduled);
        $includeFollowUp = $user->can('viewAny', VisitException::class);
        $context = $this->contexts->make($user);
        $clientNumber = $visit->client->client_number;
        $serviceDate = $scheduled->service_date->toDateString();

        return new DocumentDefinition(
            title: 'Completed Visit Report',
            view: 'documents.completed-visit',
            filename: DocumentFilename::make(
                $context->agencySlug,
                'Completed-Visit',
                $clientNumber,
                $serviceDate,
            ),
            subtitle: $visit->client->full_name,
            reference: $clientNumber.' · '.$this->settings->formatCalendarDate($scheduled->service_date),
            data: [
                'client_name' => $visit->client->full_name,
                'client_number' => $clientNumber,
                'service' => $visit->service_type,
                'dsp_name' => $visit->employee->full_name,
                'supervisor_name' => $this->visitSupervisorName($visit),
                'scheduled_window' => DirectoryPresenter::visitTimeLabel($scheduled),
                'original_clock_in' => $this->settings->formatDateTime($visit->clocked_in_at),
                'original_clock_out' => $visit->clocked_out_at === null ? null : $this->settings->formatDateTime($visit->clocked_out_at),
                'effective_clock_in' => $effective['start'] === null ? null : $this->settings->formatDateTime($effective['start']),
                'effective_clock_out' => $effective['end'] === null ? null : $this->settings->formatDateTime($effective['end']),
                'clocks_adjusted' => $effective['adjusted'],
                'original_duration' => $this->minutesLabel(
                    $visit->clocked_out_at === null
                        ? null
                        : (int) $visit->clocked_in_at->diffInMinutes($visit->clocked_out_at)
                ),
                'effective_duration' => $this->minutesLabel($this->attendanceStatuses->workedMinutes($scheduled)),
                'visit_status' => Str::headline($visit->status->value),
                'task_summary' => $this->taskSummary($visit),
                'completed_tasks' => $this->tasksOf($visit, VisitTaskStatus::Completed),
                'skipped_tasks' => $this->tasksOf($visit, VisitTaskStatus::Skipped),
                'pending_tasks' => $this->tasksOf($visit, VisitTaskStatus::Pending),
                'visit_notes' => $visit->visit_notes,
                'handover_note' => $visit->handover_note,
                'exceptions' => $this->exceptions($visit, $includeFollowUp),
                'include_follow_up' => $includeFollowUp,
                'clock_in_location' => DirectoryPresenter::gpsStatusLabel($visit->clock_in_location_status->value),
                'clock_out_location' => $visit->clock_out_location_status === null
                    ? null
                    : DirectoryPresenter::gpsStatusLabel($visit->clock_out_location_status->value),
                'clock_in_unavailable_reason' => $visit->clock_in_unavailable_reason,
                'clock_out_unavailable_reason' => $visit->clock_out_unavailable_reason,
            ],
            context: $context,
        );
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     */
    public function hoursAttendance(User $user, array $filters): DocumentDefinition
    {
        abort_unless($user->can('viewAny', OperationalReport::class), 403);

        $rows = [];
        $scheduledMinutes = 0;
        $workedMinutes = 0;
        $employeeName = null;
        $employeeNumber = null;
        $supervisorName = null;

        foreach ($this->attendance->query($user, $filters)
            ->with([
                'client',
                'employee.supervisor',
                'supervisor',
                'shiftTemplate',
                'visit.exceptions',
                'attendanceCorrections.requestedBy',
                'attendanceCorrections.reviewedBy',
            ])
            ->orderBy('employee_id')
            ->orderBy('service_date')
            ->orderBy('id')
            ->get() as $scheduled) {
            $serialized = $this->attendance->serialize($scheduled, $user);
            $status = $filters['status'];

            if ($status !== '' && $serialized['status'] !== $status) {
                continue;
            }

            $rowScheduled = max(0, $scheduled->durationMinutes());
            $rowWorked = $this->attendanceStatuses->workedMinutes($scheduled);
            $scheduledMinutes += $rowScheduled;

            if ($rowWorked !== null) {
                $workedMinutes += $rowWorked;
            }

            $employeeName = $scheduled->employee->full_name;
            $employeeNumber = $scheduled->employee->employee_number;
            $supervisorName = $serialized['supervisor_name'];

            $rows[] = [
                'employee_name' => $scheduled->employee->full_name,
                'employee_number' => $scheduled->employee->employee_number,
                'supervisor_name' => $serialized['supervisor_name'],
                'service_date' => $scheduled->service_date->toDateString(),
                'service_date_label' => $this->settings->formatCalendarDate($scheduled->service_date),
                'client_name' => $scheduled->client->full_name,
                'client_number' => $scheduled->client->client_number,
                'scheduled_hours' => $this->decimalHours($rowScheduled),
                'actual_hours' => $this->decimalHours($rowWorked),
                'status_label' => $serialized['status_label'],
                'corrected' => (bool) $serialized['is_adjusted'],
                'has_exception' => (bool) $serialized['has_exception'],
            ];
        }

        $singleEmployee = $filters['employee_id'] !== '' && ctype_digit($filters['employee_id']) && $rows !== [];
        $context = $this->contexts->make($user);
        $from = $filters['from'] !== '' ? $filters['from'] : 'all';
        $to = $filters['to'] !== '' ? $filters['to'] : $from;
        $subject = $singleEmployee ? (string) $employeeName : 'All-Employees';

        return new DocumentDefinition(
            title: 'Employee Hours & Attendance Report',
            view: 'documents.hours-attendance',
            filename: DocumentFilename::make(
                $context->agencySlug,
                'Hours',
                $subject,
                $from,
                'to',
                $to,
            ),
            subtitle: $singleEmployee ? (string) $employeeName : 'Permitted caseload',
            reference: $from.' to '.$to,
            data: [
                'employee_name' => $singleEmployee ? $employeeName : 'All employees in scope',
                'employee_number' => $singleEmployee ? $employeeNumber : null,
                'supervisor_name' => $singleEmployee ? $supervisorName : ($user->isSupervisor() ? $user->employee?->full_name : null),
                'date_range' => $from.' to '.$to,
                'rows' => $rows,
                'total_scheduled_hours' => $this->decimalHours($scheduledMinutes),
                'total_actual_hours' => $this->decimalHours($workedMinutes),
                'row_count' => count($rows),
            ],
            context: $context,
        );
    }

    /**
     * @return array{total: int, completed: int, skipped: int, pending: int}
     */
    private function taskSummary(Visit $visit): array
    {
        $tasks = $visit->tasks;

        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', VisitTaskStatus::Completed)->count(),
            'skipped' => $tasks->where('status', VisitTaskStatus::Skipped)->count(),
            'pending' => $tasks->where('status', VisitTaskStatus::Pending)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tasksOf(Visit $visit, VisitTaskStatus $status): array
    {
        $rows = [];

        foreach ($visit->tasks as $task) {
            if ($task->status !== $status) {
                continue;
            }

            $rows[] = [
                'title' => $task->title,
                'instructions' => $task->instructions,
                'is_required' => $task->is_required,
                'is_critical' => $task->is_critical,
                'is_one_off' => $task->scheduled_visit_one_off_task_id !== null,
                'completion_note' => $task->completion_note,
                'skip_reason' => $task->skipReason?->name,
                'skip_comment' => $task->skip_comment,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exceptions(Visit $visit, bool $includeFollowUp): array
    {
        $rows = [];

        foreach ($visit->exceptions as $exception) {
            $row = [
                'type_label' => $exception->type->label(),
                'status_label' => $exception->status->label(),
                'message' => $exception->message,
                'task_title' => $exception->visitTask?->title,
            ];

            if ($includeFollowUp) {
                $row['review_notes'] = $exception->review_notes;
                $row['resolution_notes'] = $exception->resolution_notes;
                $row['reviewed_by_name'] = $exception->reviewedBy?->name;
                $row['resolved_by_name'] = $exception->resolvedBy?->name;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function visitSupervisorName(Visit $visit): ?string
    {
        $scheduledSupervisor = $visit->scheduledVisit->supervisor;

        if ($scheduledSupervisor instanceof Employee) {
            return $scheduledSupervisor->full_name;
        }

        $clientSupervisor = $visit->client->supervisor;

        return $clientSupervisor instanceof Employee ? $clientSupervisor->full_name : null;
    }

    private function minutesLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = intdiv(max(0, $minutes), 60);
        $remainder = max(0, $minutes) % 60;

        return sprintf('%dh %02dm', $hours, $remainder);
    }

    private function decimalHours(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return number_format(max(0, $minutes) / 60, 2, '.', '');
    }
}
