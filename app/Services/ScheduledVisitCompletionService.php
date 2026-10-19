<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\ScheduledVisit;
use App\Models\Visit;

/**
 * MDM 05 §10.6 — parent Scheduled Visit completes only when no linked segment
 * remains open and no handoff is awaiting the replacement DSP.
 */
class ScheduledVisitCompletionService
{
    public function completeIfSettled(ScheduledVisit $scheduledVisit): void
    {
        $scheduledVisit->refresh();
        $scheduledVisit->loadMissing('visits');

        if ($scheduledVisit->status === ScheduledVisitStatus::Cancelled) {
            return;
        }

        if ($scheduledVisit->handoff_pending) {
            return;
        }

        if ($scheduledVisit->visits->contains(
            fn (Visit $visit): bool => $visit->status === VisitStatus::InProgress,
        )) {
            return;
        }

        // No Visit started yet: leave Scheduled / Staffing Needed untouched.
        if ($scheduledVisit->visits->isEmpty()) {
            return;
        }

        if ($scheduledVisit->status !== ScheduledVisitStatus::Completed) {
            $scheduledVisit->update([
                'status' => ScheduledVisitStatus::Completed,
            ]);
        }
    }
}
