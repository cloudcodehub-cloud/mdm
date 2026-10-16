<?php

namespace App\Services;

use App\Enums\EmployeeAdminHistoryCategory;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLifecycleService
{
    public function __construct(
        private SettingsService $settings,
        private EmployeeAdminHistoryService $history,
        private VisitAssignmentService $assignments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function impactPreview(Employee $employee, EmploymentStatus $target, ?string $effectiveOn = null): array
    {
        $today = $this->settings->today();
        $effective = $effectiveOn !== null && $effectiveOn !== ''
            ? Carbon::parse($effectiveOn)->toDateString()
            : $today;

        $futureVisits = ScheduledVisit::query()
            ->where('employee_id', $employee->id)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $effective)
            ->with(['client', 'shiftTemplate'])
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $activeVisit = Visit::query()
            ->where('employee_id', $employee->id)
            ->where('status', VisitStatus::InProgress)
            ->with('client')
            ->first();

        $supervisedDsps = $employee->job_type === JobType::Supervisor
            ? Employee::query()
                ->where('supervisor_id', $employee->id)
                ->where('employment_status', '!=', EmploymentStatus::Archived)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'employment_status'])
            : collect();

        $consequences = match ($target) {
            EmploymentStatus::Suspended => [
                'Employee remains employed but cannot perform service while suspended.',
                'Future DSP assignments from the effective date move to Staffing Needed; assignment history is preserved.',
                'Login access is not automatically revoked by Suspension.',
                'Active in-progress visits are not automatically clocked out.',
            ],
            EmploymentStatus::EmploymentEnded => [
                'Employment relationship ends; the employee cannot perform future service.',
                'Linked User login is deactivated when Employment Ended becomes effective.',
                'Future DSP assignments from the effective date move to Staffing Needed; history remains intact.',
                'Active in-progress visits are not automatically clocked out or deleted.',
            ],
            EmploymentStatus::Archived => [
                'Archive removes the employee from ordinary active directories and operations.',
                'Archive is not interchangeable with Employment Ended.',
                'Future DSP assignments from the effective date move to Staffing Needed where applicable.',
                'Historical visits, attendance, compliance, and audit records remain preserved.',
            ],
            EmploymentStatus::Active => [
                'Prospective service eligibility is restored only if other eligibility rules (compliance, leave, etc.) permit.',
                'Login access is restored for linked accounts when returning from Employment Ended or Archived.',
                'Previously released Staffing Needed visits are not automatically reassigned.',
            ],
        };

        return [
            'employee_id' => $employee->id,
            'current_status' => $employee->employment_status->value,
            'current_status_label' => $employee->employment_status->label(),
            'target_status' => $target->value,
            'target_status_label' => $target->label(),
            'effective_on' => $effective,
            'future_scheduled_visits' => $futureVisits->map(function (ScheduledVisit $visit): array {
                return [
                    'id' => $visit->id,
                    'service_date' => $visit->service_date->toDateString(),
                    'service_type' => $visit->service_type,
                    'client_name' => $visit->client?->full_name,
                    'time_label' => $this->settings->formatTime($visit->startsAtOn()).' – '.$this->settings->formatTime($visit->endsAtOn()),
                ];
            })->values()->all(),
            'future_scheduled_count' => $futureVisits->count(),
            'will_release_to_staffing_needed' => $target->releasesFutureAssignments() && $futureVisits->isNotEmpty(),
            'active_visit' => $activeVisit === null ? null : [
                'id' => $activeVisit->id,
                'client_name' => $activeVisit->client?->full_name,
                'warning' => 'An active visit is in progress. Lifecycle change does not automatically end, clock out, or invalidate that visit.',
            ],
            'supervised_dsps' => $supervisedDsps->map(fn (Employee $dsp): array => [
                'id' => $dsp->id,
                'name' => $dsp->full_name,
                'employee_number' => $dsp->employee_number,
                'employment_status' => $dsp->employment_status->value,
            ])->values()->all(),
            'login_consequence' => match ($target) {
                EmploymentStatus::EmploymentEnded, EmploymentStatus::Archived => 'Linked login access will be blocked while this status remains effective.',
                EmploymentStatus::Suspended => 'Login access is retained during Suspension.',
                EmploymentStatus::Active => 'Linked login access is permitted again (subject to account existence).',
            },
            'consequences' => $consequences,
        ];
    }

    /**
     * @param  array{employment_status?: string, status?: string, reason?: string|null, effective_on?: string|null, terminated_on?: string|null}  $payload
     */
    public function transition(Employee $employee, User $actor, array $payload, ?CarbonInterface $now = null): Employee
    {
        $raw = (string) ($payload['employment_status'] ?? $payload['status'] ?? '');
        $target = EmploymentStatus::tryFrom($raw);

        if ($target === null) {
            throw ValidationException::withMessages([
                'employment_status' => 'Select a valid employment lifecycle status.',
            ]);
        }

        $now = $now ?? $this->settings->now();

        if ($target === $employee->employment_status) {
            return $employee;
        }

        $this->assertTransitionAllowed($employee, $target, $actor);

        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;
        $reason = $reason === '' ? null : $reason;

        if (in_array($target, [EmploymentStatus::Suspended, EmploymentStatus::EmploymentEnded, EmploymentStatus::Archived], true)
            && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Provide a reason for this lifecycle change.',
            ]);
        }

        $effectiveOn = isset($payload['effective_on']) && filled($payload['effective_on'])
            ? Carbon::parse((string) $payload['effective_on'])->toDateString()
            : (isset($payload['terminated_on']) && filled($payload['terminated_on'])
                ? Carbon::parse((string) $payload['terminated_on'])->toDateString()
                : $this->settings->today());

        return DB::transaction(function () use ($employee, $actor, $target, $reason, $now, $effectiveOn): Employee {
            $previous = $employee->employment_status;

            $employee->forceFill([
                'employment_status' => $target,
                'status_reason' => $reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
                'terminated_on' => $target === EmploymentStatus::EmploymentEnded
                    ? $effectiveOn
                    : ($target === EmploymentStatus::Active ? null : $employee->terminated_on),
            ])->save();

            $this->history->record($employee, $actor, [
                'category' => EmployeeAdminHistoryCategory::Lifecycle,
                'summary' => 'Employee lifecycle changed to '.$target->label(),
                'old_value' => $previous->label(),
                'new_value' => $target->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $employee,
            ]);

            if ($target->releasesFutureAssignments()) {
                $released = $this->assignments->releaseFutureAssignmentsToStaffingNeeded(
                    $employee,
                    $actor,
                    $effectiveOn,
                    'Employment lifecycle: '.$target->label().($reason ? ' — '.$reason : ''),
                );

                if ($released > 0) {
                    $this->history->record($employee, $actor, [
                        'category' => EmployeeAdminHistoryCategory::SchedulingImpact,
                        'summary' => $released.' future scheduled visit(s) moved to Staffing Needed',
                        'old_value' => (string) $released,
                        'new_value' => 'staffing_needed',
                        'reason' => $reason,
                        'effective_on' => $effectiveOn,
                        'meta' => ['released_count' => $released],
                    ]);
                }
            }

            if (in_array($target, [EmploymentStatus::EmploymentEnded, EmploymentStatus::Archived], true)
                && $employee->user_id !== null) {
                $this->history->record($employee, $actor, [
                    'category' => EmployeeAdminHistoryCategory::Login,
                    'summary' => 'Linked login access deactivated by '.$target->label(),
                    'old_value' => 'active',
                    'new_value' => 'deactivated',
                    'reason' => $reason,
                    'effective_on' => $effectiveOn,
                ]);
            }

            if ($previous !== EmploymentStatus::Active
                && $target === EmploymentStatus::Active
                && $employee->user_id !== null) {
                $this->history->record($employee, $actor, [
                    'category' => EmployeeAdminHistoryCategory::Login,
                    'summary' => 'Linked login access restored with Active employment',
                    'old_value' => 'deactivated',
                    'new_value' => 'active',
                    'reason' => $reason,
                    'effective_on' => $effectiveOn,
                ]);
            }

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    private function assertTransitionAllowed(Employee $employee, EmploymentStatus $target, User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'employment_status' => 'Only administrators may change employee lifecycle status.',
            ]);
        }

        $current = $employee->employment_status;

        $allowed = match ($current) {
            EmploymentStatus::Active => [
                EmploymentStatus::Suspended,
                EmploymentStatus::EmploymentEnded,
                EmploymentStatus::Archived,
            ],
            EmploymentStatus::Suspended => [
                EmploymentStatus::Active,
                EmploymentStatus::EmploymentEnded,
                EmploymentStatus::Archived,
            ],
            EmploymentStatus::EmploymentEnded => [
                EmploymentStatus::Active,
                EmploymentStatus::Archived,
            ],
            EmploymentStatus::Archived => [
                EmploymentStatus::Active,
            ],
        };

        if (! in_array($target, $allowed, true)) {
            throw ValidationException::withMessages([
                'employment_status' => sprintf(
                    'Cannot change employment status from %s to %s.',
                    $current->label(),
                    $target->label(),
                ),
            ]);
        }
    }
}
