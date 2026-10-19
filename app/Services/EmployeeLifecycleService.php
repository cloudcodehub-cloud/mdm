<?php

namespace App\Services;

use App\Enums\EmployeeAdminHistoryCategory;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\Role;
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
        private SupervisorManagementService $supervisors,
    ) {}

    /**
     * MDM 03 Archive Pending: live operational work that blocks safe archival right now.
     *
     * - DSP / any employee: an in-progress Visit (active visit must finish first).
     * - Supervisor: DSPs/clients still on the caseload (must be reassigned/covered).
     *
     * @return list<array{type: string, label: string, count: int, visit_id?: int}>
     */
    public function archiveBlockers(Employee $employee): array
    {
        $blockers = [];

        $activeVisit = Visit::query()
            ->where('employee_id', $employee->id)
            ->where('status', VisitStatus::InProgress)
            ->with('client')
            ->first();

        if ($activeVisit !== null) {
            $blockers[] = [
                'type' => 'active_visit',
                'label' => 'Active visit'.($activeVisit->client !== null ? ' with '.$activeVisit->client->full_name : '').' must finish',
                'count' => 1,
                'visit_id' => $activeVisit->id,
            ];
        }

        if ($employee->job_type === JobType::Supervisor) {
            $dependencies = $this->supervisors->liveArchiveDependencies($employee);

            if ($dependencies['dsp_count'] + $dependencies['other_report_count'] > 0) {
                $count = $dependencies['dsp_count'] + $dependencies['other_report_count'];
                $blockers[] = [
                    'type' => 'supervisor_team',
                    'label' => $count.' supervised employee(s) must be reassigned',
                    'count' => $count,
                ];
            }

            if ($dependencies['client_count'] > 0) {
                $blockers[] = [
                    'type' => 'supervisor_caseload',
                    'label' => $dependencies['client_count'].' client(s) on the caseload must be reassigned',
                    'count' => $dependencies['client_count'],
                ];
            }
        }

        return $blockers;
    }

    /**
     * @return array<string, mixed>
     */
    public function impactPreview(Employee $employee, EmploymentStatus $target, ?string $effectiveOn = null): array
    {
        // Archive Pending is applied automatically by an Archive request; preview it as Archive.
        if ($target === EmploymentStatus::ArchivePending) {
            $target = EmploymentStatus::Archived;
        }

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

        $archiveBlockers = $target === EmploymentStatus::Archived && ! $employee->isArchivePending()
            ? $this->archiveBlockers($employee)
            : [];
        $willEnterArchivePending = $archiveBlockers !== [];

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
            EmploymentStatus::Archived, EmploymentStatus::ArchivePending => $willEnterArchivePending
                ? [
                    'Live work blocks safe archival, so this record will enter Archive Pending. The archive request is accepted now; you do not need to repeat it.',
                    'Current live work stays intact. Archiving completes automatically once the blocking work ends.',
                    'Archive Pending is not Employment Ended: login stays available so live work can finish, and no new scheduling is allowed.',
                    'On final archive, future DSP assignments move to Staffing Needed. Historical visits, attendance, compliance, and audit records remain preserved.',
                ]
                : [
                    'Archive removes the employee from ordinary active directories and operations.',
                    'Archive is not interchangeable with Employment Ended.',
                    'Future DSP assignments from the effective date move to Staffing Needed where applicable.',
                    'Historical visits, attendance, compliance, and audit records remain preserved.',
                ],
            EmploymentStatus::Active => [
                'Prospective service eligibility is restored only if other eligibility rules (compliance, leave, etc.) permit.',
                'Login access is restored for linked accounts when returning from Employment Ended or Archived.',
                'Previously released Staffing Needed visits are not automatically reassigned.',
                'Returning from Archive Pending cancels the pending archive request.',
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
            'will_release_to_staffing_needed' => $target->releasesFutureAssignments()
                && $futureVisits->isNotEmpty()
                && ! $willEnterArchivePending,
            'staffing_release_deferred' => $willEnterArchivePending && $futureVisits->isNotEmpty(),
            'will_enter_archive_pending' => $willEnterArchivePending,
            'archive_blockers' => $archiveBlockers,
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
                EmploymentStatus::EmploymentEnded => 'Linked login access will be blocked while this status remains effective.',
                EmploymentStatus::Archived, EmploymentStatus::ArchivePending => $willEnterArchivePending
                    ? 'Linked login access is retained while Archive Pending so live work can finish; it is blocked once the archive completes.'
                    : 'Linked login access will be blocked while this status remains effective.',
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

        if ($target === EmploymentStatus::ArchivePending) {
            throw ValidationException::withMessages([
                'employment_status' => 'Archive Pending is applied automatically when an Admin archives a record that still has live work. Choose Archived.',
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

        if ($employee->isArchivePending()) {
            if ($target === EmploymentStatus::Archived) {
                // Already accepted. Admin does not repeat: finalize only when blockers are gone.
                $this->finalizeArchivePending($employee, $actor, $now);

                return $employee->fresh(['user', 'supervisor']) ?? $employee;
            }

            return $this->cancelArchivePending($employee, $actor, $target, $reason, $now, $effectiveOn);
        }

        if ($target === EmploymentStatus::Archived) {
            $blockers = $this->archiveBlockers($employee);

            if ($blockers !== []) {
                return $this->enterArchivePending($employee, $actor, $reason ?? '', $blockers, $now, $effectiveOn);
            }
        }

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
                $this->releaseFutureAssignments($employee, $actor, $target, $reason, $effectiveOn);
            }

            if (in_array($target, [EmploymentStatus::EmploymentEnded, EmploymentStatus::Archived], true)
                && $employee->user_id !== null) {
                $this->recordLoginDeactivated($employee, $actor, $target, $reason, $effectiveOn);
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

    /**
     * MDM 03: accept the Admin archive request but defer finalization while live work blocks it.
     * Current live work stays intact; future assignments are NOT released until final archive.
     *
     * @param  list<array<string, mixed>>  $blockers
     */
    private function enterArchivePending(
        Employee $employee,
        User $actor,
        string $reason,
        array $blockers,
        CarbonInterface $now,
        string $effectiveOn,
    ): Employee {
        return DB::transaction(function () use ($employee, $actor, $reason, $blockers, $now, $effectiveOn): Employee {
            $previous = $employee->employment_status;

            $employee->forceFill([
                'employment_status' => EmploymentStatus::ArchivePending,
                'status_reason' => $reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
                'archive_pending_at' => $now,
                'archive_pending_by_user_id' => $actor->id,
                'archive_pending_reason' => $reason,
                'archive_pending_blockers' => $blockers,
                'archive_pending_from_status' => $previous->value,
            ])->save();

            $this->history->record($employee, $actor, [
                'category' => EmployeeAdminHistoryCategory::Lifecycle,
                'summary' => 'Archive requested — Archive Pending until live work ends',
                'old_value' => $previous->label(),
                'new_value' => EmploymentStatus::ArchivePending->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $employee,
                'meta' => [
                    'blockers' => $blockers,
                    'from_status' => $previous->value,
                ],
            ]);

            return $employee->fresh(['user', 'supervisor']) ?? $employee;
        });
    }

    /**
     * Return a pending archive to Active (or the status it came from) without archiving.
     */
    private function cancelArchivePending(
        Employee $employee,
        User $actor,
        EmploymentStatus $target,
        ?string $reason,
        CarbonInterface $now,
        string $effectiveOn,
    ): Employee {
        return DB::transaction(function () use ($employee, $actor, $target, $reason, $now, $effectiveOn): Employee {
            $fromStatus = EmploymentStatus::tryFrom((string) $employee->archive_pending_from_status);

            $employee->forceFill([
                'employment_status' => $target,
                'status_reason' => $target === EmploymentStatus::Active ? $reason : $employee->status_reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
                'terminated_on' => $target === EmploymentStatus::Active ? null : $employee->terminated_on,
                ...$this->clearedArchivePendingAttributes(),
            ])->save();

            $this->history->record($employee, $actor, [
                'category' => EmployeeAdminHistoryCategory::Lifecycle,
                'summary' => 'Archive request cancelled — returned to '.$target->label(),
                'old_value' => EmploymentStatus::ArchivePending->label(),
                'new_value' => $target->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $employee,
            ]);

            if ($fromStatus === EmploymentStatus::EmploymentEnded
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

    /**
     * Complete a pending archive once nothing blocks it. Safe to call repeatedly.
     * Returns true only when the employee became Archived by this call.
     */
    public function finalizeArchivePending(Employee $employee, ?User $actor = null, ?CarbonInterface $now = null): bool
    {
        $now = $now ?? $this->settings->now();

        return DB::transaction(function () use ($employee, $actor, $now): bool {
            /** @var Employee|null $locked */
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isArchivePending()) {
                return false;
            }

            if ($this->archiveBlockers($locked) !== []) {
                return false;
            }

            $actor = $actor
                ?? $locked->archivePendingBy
                ?? User::query()->where('role', Role::Admin)->orderBy('id')->first();

            if ($actor === null) {
                return false;
            }

            $reason = $locked->archive_pending_reason ?? $locked->status_reason ?? 'Archive Pending completed';
            $fromStatus = $locked->archive_pending_from_status;
            $effectiveOn = $this->settings->today();

            $locked->forceFill([
                'employment_status' => EmploymentStatus::Archived,
                'status_reason' => $reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
                ...$this->clearedArchivePendingAttributes(),
            ])->save();

            $this->history->record($locked, $actor, [
                'category' => EmployeeAdminHistoryCategory::Lifecycle,
                'summary' => 'Archive Pending completed — employee Archived automatically',
                'old_value' => EmploymentStatus::ArchivePending->label(),
                'new_value' => EmploymentStatus::Archived->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $locked,
            ]);

            $this->releaseFutureAssignments($locked, $actor, EmploymentStatus::Archived, $reason, $effectiveOn);

            if ($locked->user_id !== null && $fromStatus !== EmploymentStatus::EmploymentEnded->value) {
                $this->recordLoginDeactivated($locked, $actor, EmploymentStatus::Archived, $reason, $effectiveOn);
            }

            return true;
        });
    }

    /**
     * @return array<string, null>
     */
    private function clearedArchivePendingAttributes(): array
    {
        return [
            'archive_pending_at' => null,
            'archive_pending_by_user_id' => null,
            'archive_pending_reason' => null,
            'archive_pending_blockers' => null,
            'archive_pending_from_status' => null,
        ];
    }

    private function releaseFutureAssignments(
        Employee $employee,
        User $actor,
        EmploymentStatus $target,
        ?string $reason,
        string $effectiveOn,
    ): void {
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

    private function recordLoginDeactivated(
        Employee $employee,
        User $actor,
        EmploymentStatus $target,
        ?string $reason,
        string $effectiveOn,
    ): void {
        $this->history->record($employee, $actor, [
            'category' => EmployeeAdminHistoryCategory::Login,
            'summary' => 'Linked login access deactivated by '.$target->label(),
            'old_value' => 'active',
            'new_value' => 'deactivated',
            'reason' => $reason,
            'effective_on' => $effectiveOn,
        ]);
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
            // Pending archive finishes automatically; Admin may only confirm it or cancel the request.
            EmploymentStatus::ArchivePending => array_values(array_filter([
                EmploymentStatus::Active,
                EmploymentStatus::Archived,
                EmploymentStatus::tryFrom((string) $employee->archive_pending_from_status),
            ])),
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
