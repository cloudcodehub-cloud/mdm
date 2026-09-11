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
        $scheduledVisit->loadMissing(['visit.exceptions', 'attendanceCorrections']);

        if ($this->approvedAdjustment($scheduledVisit) !== null) {
            return AttendanceStatus::ManuallyAdjusted;
        }

        $execution = $scheduledVisit->visit;

        if ($this->hasOpenException($execution)) {
            return AttendanceStatus::Exception;
        }

        if ($execution?->status === VisitStatus::InProgress
            || $scheduledVisit->status === ScheduledVisitStatus::InProgress) {
            return AttendanceStatus::InProgress;
        }

        if ($execution?->status === VisitStatus::Completed
            || $scheduledVisit->status === ScheduledVisitStatus::Completed) {
            return AttendanceStatus::Completed;
        }

        if ($execution !== null) {
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
     * @return array{start: CarbonInterface|null, end: CarbonInterface|null, adjusted: bool}
     */
    public function effectiveTimes(ScheduledVisit $scheduledVisit): array
    {
        $scheduledVisit->loadMissing('visit');
        $visit = $scheduledVisit->visit;
        $adjustment = $this->approvedAdjustment($scheduledVisit);

        $originalStart = $visit?->clocked_in_at;
        $originalEnd = $visit?->clocked_out_at;

        if ($adjustment === null) {
            return [
                'start' => $originalStart,
                'end' => $originalEnd,
                'adjusted' => false,
            ];
        }

        return [
            'start' => $adjustment->requested_clocked_in_at ?? $originalStart,
            'end' => $adjustment->requested_clocked_out_at ?? $originalEnd,
            'adjusted' => true,
        ];
    }

    public function workedMinutes(ScheduledVisit $scheduledVisit): ?int
    {
        $times = $this->effectiveTimes($scheduledVisit);

        if ($times['start'] === null || $times['end'] === null) {
            return null;
        }

        return max(0, (int) $times['start']->diffInMinutes($times['end']));
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
