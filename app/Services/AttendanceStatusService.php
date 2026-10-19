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

    public function approvedAdjustmentForVisit(Visit $visit): ?AttendanceCorrection
    {
        $visit->loadMissing('scheduledVisit.attendanceCorrections');
        $corrections = $visit->scheduledVisit?->attendanceCorrections
            ?? AttendanceCorrection::query()->where('visit_id', $visit->id)->get();

        return $corrections
            ->filter(fn (AttendanceCorrection $correction): bool => $correction->isApproved()
                && (int) $correction->visit_id === (int) $visit->id
                && $correction->requestsTimeChange())
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
     * Effective window for one Visit segment (approved time overlay when present).
     * MDM 05-22 — pending corrections are ignored.
     *
     * @return array{start: CarbonInterface|null, end: CarbonInterface|null, adjusted: bool}
     */
    public function segmentEffectiveWindow(Visit $visit): array
    {
        $adjustment = $this->approvedAdjustmentForVisit($visit);
        $start = $adjustment?->requested_clocked_in_at ?? $visit->clocked_in_at;
        $end = $adjustment?->requested_clocked_out_at ?? $visit->effectiveEndedAt();

        return [
            'start' => $start,
            'end' => $end,
            'adjusted' => $adjustment !== null,
        ];
    }

    /**
     * Aggregate window across linked segments (earliest start → latest effective end).
     * Gaps are not filled; see workedMinutes() for non-overlapping sum.
     *
     * @return array{start: CarbonInterface|null, end: CarbonInterface|null, adjusted: bool}
     */
    public function effectiveTimes(ScheduledVisit $scheduledVisit): array
    {
        $scheduledVisit->loadMissing(['visits', 'attendanceCorrections']);
        $segments = $scheduledVisit->visits->sortBy('id')->values();

        if ($segments->isEmpty()) {
            return ['start' => null, 'end' => null, 'adjusted' => false];
        }

        $starts = [];
        $ends = [];
        $adjusted = false;

        foreach ($segments as $visit) {
            $window = $this->segmentEffectiveWindow($visit);
            if ($window['start'] !== null) {
                $starts[] = $window['start'];
            }
            if ($window['end'] !== null) {
                $ends[] = $window['end'];
            }
            $adjusted = $adjusted || $window['adjusted'];
        }

        return [
            'start' => $starts === [] ? null : collect($starts)->sortBy(fn (CarbonInterface $t) => $t->getTimestamp())->first(),
            'end' => $ends === [] ? null : collect($ends)->sortByDesc(fn (CarbonInterface $t) => $t->getTimestamp())->first(),
            'adjusted' => $adjusted,
        ];
    }

    /**
     * Sum worked minutes across linked Visit segments. Gaps between segments are
     * unserved and are never fabricated into hours (MDM 05 §10.7).
     * Per-segment approved corrections overlay only that segment.
     */
    public function workedMinutes(ScheduledVisit $scheduledVisit): ?int
    {
        $scheduledVisit->loadMissing(['visits', 'attendanceCorrections']);
        if ($scheduledVisit->visits->isEmpty()) {
            return null;
        }

        $total = 0;
        $anyComplete = false;

        foreach ($scheduledVisit->visits as $visit) {
            if ($visit->status === VisitStatus::InProgress) {
                return null;
            }

            $window = $this->segmentEffectiveWindow($visit);
            if ($window['start'] === null || $window['end'] === null) {
                continue;
            }

            $total += max(0, (int) $window['start']->diffInMinutes($window['end']));
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
