<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
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

    public function coverageAttention(ScheduledVisit $visit): void
    {
        $this->notifySupervisor(
            $visit,
            'Coverage needs attention',
            ($visit->attention_reason ?? 'A scheduled visit needs staffing attention.'),
            'schedule.visit.'.$visit->id.'.attention',
        );
    }

    private function notifySupervisor(ScheduledVisit $visit, string $title, string $body, string $key): void
    {
        $visit->loadMissing(['supervisor.user', 'client.supervisor.user']);
        $user = $visit->supervisor?->user;

        if ($user === null) {
            $user = $visit->client->supervisor?->user;
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

    private function notifyAdmins(string $title, string $body, string $key, string $url): void
    {
        $admins = User::query()->where('role', \App\Enums\Role::Admin)->get();

        foreach ($admins as $admin) {
            $this->notifications->notify(
                $admin,
                InAppNotificationType::Schedule,
                $title,
                $body,
                $key.'.admin.'.$admin->id,
                $url,
            );
        }
    }
}
