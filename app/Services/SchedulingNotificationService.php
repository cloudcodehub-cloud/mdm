<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\EmployeeTimeOff;
use App\Models\ScheduledVisit;
use App\Models\User;

class SchedulingNotificationService
{
    public function __construct(private InAppNotificationService $notifications) {}

    public function visitAssigned(ScheduledVisit $visit): void
    {
        $visit->loadMissing(['employee.user', 'client']);
        $dspUser = $visit->employee->user;

        if ($dspUser === null) {
            return;
        }

        $this->notifications->notify(
            $dspUser,
            InAppNotificationType::Schedule,
            'New visit assigned',
            $visit->client->full_name.' · '.$visit->service_date->toDateString(),
            'schedule.visit.'.$visit->id.'.assigned',
            route('scheduled-visits.show', $visit),
        );
    }

    public function visitReassigned(ScheduledVisit $visit, Employee $previous): void
    {
        $visit->loadMissing(['employee.user', 'client']);

        if ($visit->employee->user !== null) {
            $this->notifications->notify(
                $visit->employee->user,
                InAppNotificationType::Schedule,
                'Visit assigned to you',
                $visit->client->full_name.' · '.$visit->service_date->toDateString(),
                'schedule.visit.'.$visit->id.'.assigned',
                route('scheduled-visits.show', $visit),
            );
        }

        if ($previous->user !== null && $previous->id !== $visit->employee_id) {
            $this->notifications->notify(
                $previous->user,
                InAppNotificationType::Schedule,
                'Visit reassigned',
                $visit->client->full_name.' is no longer on your schedule for '.$visit->service_date->toDateString().'.',
                'schedule.visit.'.$visit->id.'.reassigned-from',
                route('scheduled-visits.index'),
            );
        }

        $this->notifySupervisor(
            $visit,
            'Visit reassignment',
            $visit->client->full_name.' was reassigned for '.$visit->service_date->toDateString().'.',
            'schedule.visit.'.$visit->id.'.reassigned',
        );
    }

    public function visitCancelled(ScheduledVisit $visit): void
    {
        $visit->loadMissing(['employee.user', 'client']);

        if ($visit->employee->user !== null) {
            $this->notifications->notify(
                $visit->employee->user,
                InAppNotificationType::Schedule,
                'Visit cancelled',
                $visit->client->full_name.' · '.$visit->service_date->toDateString(),
                'schedule.visit.'.$visit->id.'.cancelled',
                route('scheduled-visits.show', $visit),
            );
        }
    }

    public function visitTimeChanged(ScheduledVisit $visit): void
    {
        $visit->loadMissing(['employee.user', 'client']);

        if ($visit->employee->user === null) {
            return;
        }

        $this->notifications->notify(
            $visit->employee->user,
            InAppNotificationType::Schedule,
            'Visit time changed',
            $visit->client->full_name.' · '.$visit->service_date->toDateString(),
            'schedule.visit.'.$visit->id.'.time',
            route('scheduled-visits.show', $visit),
        );
    }

    public function availabilitySubmitted(DspAvailabilityRequest $request): void
    {
        $request->loadMissing(['employee.supervisor.user', 'employee']);
        $supervisorUser = $request->employee->supervisor?->user;

        if ($supervisorUser === null) {
            $this->notifyAdmins(
                'Availability change request',
                $request->employee->full_name.' submitted an availability change.',
                'availability.request.'.$request->id,
                route('availability-requests.index'),
            );

            return;
        }

        $this->notifications->notify(
            $supervisorUser,
            InAppNotificationType::Availability,
            'Availability change request',
            $request->employee->full_name.' submitted an availability change.',
            'availability.request.'.$request->id,
            route('availability-requests.index'),
        );
    }

    public function timeOffSubmitted(EmployeeTimeOff $timeOff): void
    {
        $timeOff->loadMissing(['employee.supervisor.user', 'employee']);
        $supervisorUser = $timeOff->employee->supervisor?->user;

        if ($supervisorUser === null) {
            $this->notifyAdmins(
                'Time-off request',
                $timeOff->employee->full_name.' requested time off.',
                'availability.timeoff.'.$timeOff->id,
                route('availability-requests.index'),
            );

            return;
        }

        $this->notifications->notify(
            $supervisorUser,
            InAppNotificationType::Availability,
            'Time-off request',
            $timeOff->employee->full_name.' requested time off.',
            'availability.timeoff.'.$timeOff->id,
            route('availability-requests.index'),
        );
    }

    public function profileChangeSubmitted(EmployeeProfileChangeRequest $request): void
    {
        $request->loadMissing(['employee.supervisor.user', 'employee']);
        $supervisorUser = $request->employee->supervisor?->user;

        if ($supervisorUser === null) {
            $this->notifyAdmins(
                'Personal information change request',
                $request->employee->full_name.' submitted a personal information change.',
                'profile.change.'.$request->id,
                route('profile-change-requests.index'),
                InAppNotificationType::Profile,
            );

            return;
        }

        $this->notifications->notify(
            $supervisorUser,
            InAppNotificationType::Profile,
            'Personal information change request',
            $request->employee->full_name.' submitted a personal information change.',
            'profile.change.'.$request->id,
            route('profile-change-requests.index'),
        );
    }

    public function complianceEvidenceSubmitted(Employee $employee, string $kind): void
    {
        $this->notifyAdmins(
            'Compliance evidence submitted',
            $employee->full_name.' submitted '.$kind.' evidence for verification.',
            'compliance.evidence.'.$employee->id.'.'.now()->timestamp,
            route('employees.show', $employee),
            InAppNotificationType::Compliance,
        );
    }

    public function coverageAttention(ScheduledVisit $visit): void
    {
        $this->notifySupervisor(
            $visit,
            'Coverage needs attention',
            ($visit->attention_reason ?? 'A scheduled visit needs staffing attention.'),
            'schedule.visit.'.$visit->id.'.attention',
        );
    }

    public function complianceHardStopStaffing(
        Employee $employee,
        ?ScheduledVisit $graceVisit,
        int $atRiskCount,
        string $summary,
    ): void {
        $employee->loadMissing(['supervisor.user']);
        $graceDate = $graceVisit?->service_date?->toDateString() ?? 'none';
        $body = $employee->full_name.' has a newly effective Hard Stop. '
            .'Nearest visit '.$graceDate.' preserved as Grace Visit; '
            .$atRiskCount.' later visit(s) marked At Risk / Action Required. '
            .$summary;

        $url = $graceVisit !== null
            ? route('scheduled-visits.show', $graceVisit)
            : route('employees.show', $employee);
        $key = 'compliance.staffing.'.$employee->id.'.'.now()->timestamp;

        $supervisorUser = $employee->supervisor?->user;
        if ($supervisorUser !== null) {
            $this->notifications->notify(
                $supervisorUser,
                InAppNotificationType::Compliance,
                'Urgent: Compliance Hard Stop staffing',
                $body,
                $key,
                $url,
            );
        }

        $this->notifyAdmins(
            'Compliance Hard Stop staffing',
            $body,
            $key,
            $url,
            InAppNotificationType::Compliance,
        );
    }

    public function staffingNeededFromLeave(ScheduledVisit $visit, EmployeeTimeOff $timeOff): void
    {
        $visit->loadMissing(['client', 'releasedEmployee']);
        $dspName = $timeOff->employee?->full_name
            ?? $visit->releasedEmployee?->full_name
            ?? 'DSP';

        $this->notifySupervisor(
            $visit,
            'Staffing Needed after approved leave',
            $dspName.' leave released '.$visit->client->full_name
                .' on '.$visit->service_date->toDateString().' to Staffing Needed.',
            'schedule.visit.'.$visit->id.'.staffing-needed-leave',
        );

        $this->notifyAdmins(
            'Staffing Needed after approved leave',
            $dspName.' leave released '.$visit->client->full_name
                .' on '.$visit->service_date->toDateString().' to Staffing Needed.',
            'schedule.visit.'.$visit->id.'.staffing-needed-leave',
            route('scheduled-visits.show', $visit),
        );
    }

    /**
     * MDM 04-29 / MDM 09: Agency urgency threshold reached for an unresolved Staffing Needed visit.
     *
     * Admin-only. The owning Supervisor already sees the visit on the Operations board (marked
     * Urgent / Overdue), so they are intentionally not sent a duplicate in-app notification.
     * Callers are responsible for one-time dispatch (staffing_urgency_escalated_at).
     */
    public function staffingNeededUrgencyReached(
        ScheduledVisit $visit,
        bool $overdue = false,
        ?int $thresholdHours = null,
    ): void {
        $visit->loadMissing(['client']);

        $when = $visit->service_date->toDateString();
        $reason = $overdue
            ? 'The scheduled start has passed and no DSP is assigned.'
            : 'It is within the '.($thresholdHours ?? app(SettingsService::class)->staffingNeededUrgencyHours())
                .'-hour urgency threshold before the scheduled start and no DSP is assigned.';

        $episode = $visit->staffing_urgency_escalated_at?->timestamp ?? now()->timestamp;

        $this->notifyAdmins(
            $overdue ? 'Overdue Staffing Needed' : 'Urgent Staffing Needed',
            $visit->client->full_name.' on '.$when.' still needs staffing. '.$reason,
            'schedule.visit.'.$visit->id.'.staffing-urgency.'.$episode,
            route('scheduled-visits.show', $visit),
            InAppNotificationType::StaffingUrgency,
        );
    }

    private function notifySupervisor(ScheduledVisit $visit, string $title, string $body, string $key): void
    {
        $visit->loadMissing(['supervisor.user', 'client.supervisor.user', 'releasedEmployee.supervisor.user']);
        $user = $visit->supervisor?->user;

        if ($user === null) {
            $user = $visit->client->supervisor?->user;
        }

        if ($user === null) {
            $user = $visit->releasedEmployee?->supervisor?->user;
        }

        if ($user === null) {
            $this->notifyAdmins($title, $body, $key, route('scheduled-visits.show', $visit));

            return;
        }

        $this->notifications->notify(
            $user,
            InAppNotificationType::Schedule,
            $title,
            $body,
            $key,
            route('scheduled-visits.show', $visit),
        );
    }

    private function notifyAdmins(
        string $title,
        string $body,
        string $key,
        string $url,
        InAppNotificationType $type = InAppNotificationType::Schedule,
    ): void {
        $admins = User::query()->where('role', Role::Admin)->get();

        foreach ($admins as $admin) {
            $this->notifications->notify(
                $admin,
                $type,
                $title,
                $body,
                $key.'.admin.'.$admin->id,
                $url,
            );
        }
    }
}
