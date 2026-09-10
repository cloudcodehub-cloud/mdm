<?php

namespace App\Services;

use App\Enums\OperationalVisitStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitStatus;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use Carbon\CarbonInterface;
use RuntimeException;

class VisitOperationsStatus
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{key: string, label: string}
     */
    public function forScheduledVisit(ScheduledVisit $visit, ?CarbonInterface $now = null): array
    {
        $status = $this->statusForScheduledVisit($visit, $now);

        return [
            'key' => $status->value,
            'label' => $status->label(),
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    public function forVisit(Visit $visit, ?CarbonInterface $now = null): array
    {
        $visit->loadMissing('scheduledVisit', 'exceptions');

        return $this->forScheduledVisit($visit->scheduledVisit, $now);
    }

    public function statusForScheduledVisit(ScheduledVisit $visit, ?CarbonInterface $now = null): OperationalVisitStatus
    {
        $now = $this->settings->now($now);

        if ($visit->status === ScheduledVisitStatus::Cancelled) {
            return OperationalVisitStatus::Cancelled;
        }

        $execution = $visit->relationLoaded('visit') ? $visit->visit : $visit->visit()->first();

        if ($execution !== null) {
            $execution->loadMissing('exceptions');

            $hasOpenException = $execution->exceptions
                ->contains(fn ($exception): bool => $exception->status === VisitExceptionStatus::Open);

            if ($hasOpenException) {
                return OperationalVisitStatus::Exception;
            }
        }

        if ($visit->status === ScheduledVisitStatus::Completed
            || $execution?->status === VisitStatus::Completed) {
            return OperationalVisitStatus::Completed;
        }

        if ($this->isLate($visit, $execution, $now)) {
            return OperationalVisitStatus::Late;
        }

        if ($this->needsAttention($visit, $execution, $now)) {
            return OperationalVisitStatus::Attention;
        }

        if ($visit->status === ScheduledVisitStatus::InProgress
            || $execution?->status === VisitStatus::InProgress) {
            return OperationalVisitStatus::InProgress;
        }

        return OperationalVisitStatus::Scheduled;
    }

    private function isLate(ScheduledVisit $visit, ?Visit $execution, CarbonInterface $now): bool
    {
        if ($execution !== null) {
            return false;
        }

        if ($visit->status !== ScheduledVisitStatus::Scheduled) {
            return false;
        }

        try {
            return $now->gt($visit->startsAtOn());
        } catch (RuntimeException) {
            return false;
        }
    }

    private function needsAttention(ScheduledVisit $visit, ?Visit $execution, CarbonInterface $now): bool
    {
        if ($execution === null || $execution->status !== VisitStatus::InProgress) {
            return false;
        }

        try {
            return $now->gt($visit->endsAtOn());
        } catch (RuntimeException) {
            return false;
        }
    }
}
