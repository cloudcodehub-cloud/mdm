<?php

namespace App\Services;

use App\Enums\VisitAssignmentKind;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAssignment;
use App\Models\User;

class VisitAssignmentService
{
    public function recordInitial(ScheduledVisit $visit, User $actor, ?string $reason = null): ScheduledVisitAssignment
    {
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
    ): ScheduledVisit {
        $current = $visit->assignments()->whereNull('ended_at')->first();

        if ($current === null) {
            $current = $this->recordInitial($visit, $actor, 'Original assignment');
        }

        $original = $visit->employee;

        $current->forceFill(['ended_at' => now()])->save();

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
            'replacement_reason' => $reason,
            'updated_by_user_id' => $actor->id,
            'needs_attention' => false,
            'attention_reason' => null,
        ]);

        if ($markCallOff) {
            app(DspAvailabilityService::class)->upsertException($original, [
                'exception_date' => $visit->service_date->toDateString(),
                'is_available' => false,
                'starts_at' => $visit->starts_at ?? $visit->shiftTemplate?->starts_at,
                'ends_at' => $visit->ends_at ?? $visit->shiftTemplate?->ends_at,
                'note' => 'Call-off / unavailable for visit #'.$visit->id,
            ]);
        }

        return $visit->fresh(['employee', 'assignments.employee', 'assignments.assignedBy']) ?? $visit;
    }
}
