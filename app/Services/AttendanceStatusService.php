<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use Carbon\CarbonInterface;
use RuntimeException;

class AttendanceStatusService
{
    public function __construct(private SettingsService $settings) {}

    public function statusFor(ScheduledVisit $scheduledVisit, ?CarbonInterface $now = null): AttendanceStatus
    {
        $now = $this->settings->now($now);
        $scheduledVisit->loadMissing(['visits.exceptions', 'attendanceCorrections']);

        if ($this->approvedAdjustment($scheduledVisit) !== null) {
            return AttendanceStatus::ManuallyAdjusted;
        }

        $segments = $scheduledVisit->visits;
        $active = $segments->first(fn (Visit $visit): bool => $visit->status === VisitStatus::InProgress);

        if ($segments->contains(fn (Visit $visit): bool => $this->hasOpenException($visit))) {
            return AttendanceStatus::Exception;
        }

        if ($active !== null
            || $scheduledVisit->status === ScheduledVisitStatus::InProgress
            || $scheduledVisit->handoff_pending) {
            return AttendanceStatus::InProgress;
        }

        if ($segments->contains(fn (Visit $visit): bool => $visit->status === VisitStatus::Completed)
            || $scheduledVisit->status === ScheduledVisitStatus::Completed) {
            return AttendanceStatus::Completed;
        }

        if ($segments->isNotEmpty()) {
            return AttendanceStatus::InProgress;
        }

        if ($this->isMissed($scheduledVisit, $now)) {
            return AttendanceStatus::Missed;
        }

        if ($this->isLate($scheduledVisit, $now)) {
            return AttendanceStatus::Late;
        }

        return AttendanceStatus::Scheduled;
    }

    public function approvedAdjustment(ScheduledVisit $scheduledVisit): ?AttendanceCorrection
    {
        $scheduledVisit->loadMissing('attendanceCorrections');

        return $scheduledVisit->attendanceCorrections
            ->filter(fn (AttendanceCorrection $correction): bool => $correction->isApproved())
            ->sortByDesc(fn (AttendanceCorrection $correction): int => $correction->reviewed_at?->getTimestamp() ?? 0)
            ->first();
    }

    public function pendingCorrection(ScheduledVisit $scheduledVisit): ?AttendanceCorrection
    {
        $scheduledVisit->loadMissing('attendanceCorrections');

        return $scheduledVisit->attendanceCorrections
            ->first(fn (AttendanceCorrection $correction): bool => $correction->isPending());
    }

    /**
     * Aggregate window across linked segments (earliest start → latest effective end).
     * Gaps are not filled; see workedMinutes() for non-overlapping sum.
     *
     * @return array{start: CarbonInterface|null, end: CarbonInterface|null, adjusted: bool}
     */
    public function effectiveTimes(ScheduledVisit $scheduledVisit): array
    {
        $scheduledVisit->loadMissing('visits');
        $adjustment = $this->approvedAdjustment($scheduledVisit);

        $segments = $scheduledVisit->visits->sortBy('id')->values();
        $first = $segments->first();
        $lastWithEnd = $segments
            ->filter(fn (Visit $visit): bool => $visit->effectiveEndedAt() !== null)
            ->last();

        $originalStart = $first?->clocked_in_at;
        $baseEnd = $lastWithEnd?->effectiveEndedAt();

        if ($adjustment === null) {
            return [
                'start' => $originalStart,
                'end' => $baseEnd,
                'adjusted' => false,
            ];
        }

        return [
            'start' => $adjustment->requested_clocked_in_at ?? $originalStart,
            'end' => $adjustment->requested_clocked_out_at ?? $baseEnd,
            'adjusted' => true,
        ];
    }

    /**
     * Sum worked minutes across linked Visit segments. Gaps between segments are
     * unserved and are never fabricated into hours (MDM 05 §10.7).
     */
    public function workedMinutes(ScheduledVisit $scheduledVisit): ?int
    {
        $adjustment = $this->approvedAdjustment($scheduledVisit);
        if ($adjustment !== null) {
            $times = $this->effectiveTimes($scheduledVisit);
            if ($times['start'] === null || $times['end'] === null) {
                return null;
            }

            return max(0, (int) $times['start']->diffInMinutes($times['end']));
        }

        $scheduledVisit->loadMissing('visits');
        if ($scheduledVisit->visits->isEmpty()) {
            return null;
        }

        $total = 0;
        $anyComplete = false;

        foreach ($scheduledVisit->visits as $visit) {
            if ($visit->status === VisitStatus::InProgress) {
                return null;
            }

            $end = $visit->effectiveEndedAt();
            if ($visit->clocked_in_at === null || $end === null) {
                continue;
            }

            $total += max(0, (int) $visit->clocked_in_at->diffInMinutes($end));
            $anyComplete = true;
        }

        return $anyComplete ? $total : null;
    }

    public function hasGpsIssue(?Visit $visit): bool
    {
        if ($visit === null) {
            return false;
        }

        if ($visit->clock_in_location_status !== ClockInLocationStatus::Captured) {
            return true;
        }

        if ($visit->clock_out_location_status === null) {
            return false;
        }

        return $visit->clock_out_location_status !== ClockInLocationStatus::Captured;
    }

    public function hasException(?Visit $visit): bool
    {
        if ($visit === null) {
            return false;
        }

        $visit->loadMissing('exceptions');

        return $visit->exceptions->isNotEmpty();
    }

    private function hasOpenException(?Visit $visit): bool
    {
        if ($visit === null) {
            return false;
        }

        $visit->loadMissing('exceptions');

        return $visit->exceptions->contains(
            fn ($exception): bool => $exception->status === VisitExceptionStatus::Open,
        );
    }

    private function isLate(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($visit->status !== ScheduledVisitStatus::Scheduled) {
            return false;
        }

        try {
            $start = $visit->startsAtOn();
            $end = $visit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        return $now->gt($start) && $now->lte($end);
    }

    private function isMissed(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($visit->status !== ScheduledVisitStatus::Scheduled) {
            return false;
        }

        try {
            return $now->gt($visit->endsAtOn());
        } catch (RuntimeException) {
            return false;
        }
    }
}
