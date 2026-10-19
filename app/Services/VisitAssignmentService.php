<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitAssignmentKind;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class VisitAssignmentService
{
    public function recordInitial(ScheduledVisit $visit, User $actor, ?string $reason = null): ScheduledVisitAssignment
    {
        if ($visit->employee_id === null) {
            throw new \InvalidArgumentException('Cannot record an assignment for a Staffing Needed visit without a DSP.');
        }

        return ScheduledVisitAssignment::query()->create([
            'scheduled_visit_id' => $visit->id,
            'employee_id' => $visit->employee_id,
            'assigned_by_user_id' => $actor->id,
            'kind' => VisitAssignmentKind::Assigned,
            'reason' => $reason,
            'assigned_at' => now(),
        ]);
    }

    public function reassign(
        ScheduledVisit $visit,
        Employee $replacement,
        User $actor,
        string $reason,
        VisitAssignmentKind $kind = VisitAssignmentKind::Reassigned,
        bool $markCallOff = false,
        ?string $overrideReason = null,
    ): ScheduledVisit {
        $current = $visit->assignments()->whereNull('ended_at')->first();

        if ($current === null && $visit->employee_id !== null) {
            $current = $this->recordInitial($visit, $actor, 'Original assignment');
        }

        $original = $visit->employee;

        if ($current !== null) {
            $current->forceFill(['ended_at' => now()])->save();
        }

        ScheduledVisitAssignment::query()->create([
            'scheduled_visit_id' => $visit->id,
            'employee_id' => $replacement->id,
            'assigned_by_user_id' => $actor->id,
            'kind' => $kind,
            'reason' => $reason,
            'assigned_at' => now(),
        ]);

        $visit->update([
            'employee_id' => $replacement->id,
            'released_employee_id' => null,
            'staffing_needed' => false,
            // MDM 04-29: staffed → urgency resolved; a future re-release starts a fresh episode.
            'staffing_urgency_escalated_at' => null,
            'compliance_staffing_status' => null,
            'compliance_staffing_marked_at' => null,
            'compliance_staffing_reason' => null,
            'replacement_reason' => $reason,
            'updated_by_user_id' => $actor->id,
            'needs_attention' => false,
            'attention_reason' => null,
        ]);

        if ($markCallOff && $original !== null) {
            app(DspAvailabilityService::class)->upsertException($original, [
                'exception_date' => $visit->service_date->toDateString(),
                'is_available' => false,
                'starts_at' => $visit->starts_at ?? $visit->shiftTemplate?->starts_at,
                'ends_at' => $visit->ends_at ?? $visit->shiftTemplate?->ends_at,
                'note' => 'Call-off / unavailable for visit #'.$visit->id,
            ]);
        }

        $fresh = $visit->fresh(['employee', 'assignments.employee', 'assignments.assignedBy']) ?? $visit;

        // MDM 04-19: record the Visit-specific unavailable-period override (audit + DSP notice)
        // only when the replacement actually conflicts with an approved unavailable period.
        if ($overrideReason !== null && trim($overrideReason) !== '') {
            app(AvailabilityOverrideService::class)->recordIfConflict($fresh, $actor, $overrideReason);
        }

        return $fresh;
    }

    /**
     * Release future scheduled assignments for an employee to Staffing Needed.
     * Preserves assignment history; does not cancel visits or invent clock-outs.
     */
    public function releaseFutureAssignmentsToStaffingNeeded(
        Employee $employee,
        User $actor,
        string $effectiveOn,
        string $reason,
    ): int {
        $visits = ScheduledVisit::query()
            ->where('employee_id', $employee->id)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', Carbon::parse($effectiveOn)->toDateString())
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($visits as $visit) {
            $this->releaseToStaffingNeeded($visit, $actor, $reason);
            $count++;
        }

        return $count;
    }

    public function releaseToStaffingNeeded(ScheduledVisit $visit, User $actor, string $reason): ScheduledVisit
    {
        if ($visit->employee_id === null && $visit->staffing_needed) {
            return $visit;
        }

        $current = $visit->assignments()->whereNull('ended_at')->first();

        if ($current === null && $visit->employee_id !== null) {
            $current = $this->recordInitial($visit, $actor, 'Original assignment before Staffing Needed release');
        }

        if ($current !== null) {
            $current->forceFill([
                'ended_at' => now(),
                'reason' => trim(($current->reason ? $current->reason.' | ' : '').$reason),
            ])->save();
        }

        $releasedEmployeeId = $visit->employee_id ?? $visit->released_employee_id;

        $visit->forceFill([
            'released_employee_id' => $releasedEmployeeId,
            'employee_id' => null,
            'staffing_needed' => true,
            'staffing_urgency_escalated_at' => null,
            'compliance_staffing_status' => null,
            'compliance_staffing_marked_at' => null,
            'compliance_staffing_reason' => null,
            'needs_attention' => true,
            'attention_reason' => 'Staffing Needed: '.$reason,
            'updated_by_user_id' => $actor->id,
        ])->save();

        return $visit->fresh(['employee', 'assignments.employee', 'assignments.assignedBy']) ?? $visit;
    }
}
