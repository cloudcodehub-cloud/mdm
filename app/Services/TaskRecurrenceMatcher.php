<?php

namespace App\Services;

use App\Enums\TaskRecurrence;
use App\Models\CarePlanTaskTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TaskRecurrenceMatcher
{
    public function appliesOn(CarePlanTaskTemplate $template, CarbonInterface $serviceDate): bool
    {
        $template->loadMissing('carePlan');
        $anchor = Carbon::parse($template->carePlan->starts_on)->startOfDay();
        $day = Carbon::parse($serviceDate)->startOfDay();

        if ($day->lt($anchor)) {
            return false;
        }

        $weekdays = $this->weekdays($template->weekdays);

        return match ($template->recurrence) {
            TaskRecurrence::Daily => $this->weekdayAllows($weekdays, $day),
            TaskRecurrence::Weekly => $this->intervalWeekApplies(
                $anchor,
                $day,
                $weekdays,
                $template->interval_weeks ?: 1,
            ),
            TaskRecurrence::Biweekly => $this->intervalWeekApplies(
                $anchor,
                $day,
                $weekdays,
                $template->interval_weeks ?: 2,
            ),
            TaskRecurrence::Monthly => $this->monthlyApplies($anchor, $day),
            TaskRecurrence::Quarterly => $this->quarterlyApplies($anchor, $day),
            TaskRecurrence::Annual => $anchor->month === $day->month && $this->monthlyApplies($anchor, $day),
            TaskRecurrence::Custom => $this->weekdayAllows($weekdays, $day),
        };
    }

    public function nextAfter(CarePlanTaskTemplate $template, CarbonInterface $from): ?CarbonInterface
    {
        $day = Carbon::parse($from)->startOfDay()->addDay();
        $limit = $day->copy()->addYear();

        while ($day->lte($limit)) {
            if ($this->appliesOn($template, $day)) {
                return $day->copy();
            }

            $day->addDay();
        }

        return null;
    }

    /**
     * @param  array<int, mixed>|null  $weekdays
     * @return list<int>
     */
    private function weekdays(?array $weekdays): array
    {
        if ($weekdays === null) {
            return [];
        }

        $normalized = [];

        foreach ($weekdays as $day) {
            $value = (int) $day;

            if ($value >= 0 && $value <= 6) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<int>  $weekdays
     */
    private function weekdayAllows(array $weekdays, CarbonInterface $day): bool
    {
        if ($weekdays === []) {
            return true;
        }

        return in_array($day->dayOfWeek, $weekdays, true);
    }

    /**
     * @param  list<int>  $weekdays
     */
    private function intervalWeekApplies(
        CarbonInterface $anchor,
        CarbonInterface $day,
        array $weekdays,
        int $intervalWeeks,
    ): bool {
        $targets = $weekdays === [] ? [$anchor->dayOfWeek] : $weekdays;

        if (! in_array($day->dayOfWeek, $targets, true)) {
            return false;
        }

        $interval = max(1, $intervalWeeks);
        $weeks = intdiv((int) $anchor->diffInDays($day), 7);

        return $weeks % $interval === 0;
    }

    private function monthlyApplies(CarbonInterface $anchor, CarbonInterface $day): bool
    {
        if ($anchor->day === $day->day) {
            return true;
        }

        return $anchor->day > $day->daysInMonth && $day->isLastOfMonth();
    }

    private function quarterlyApplies(CarbonInterface $anchor, CarbonInterface $day): bool
    {
        if (! $this->monthlyApplies($anchor, $day)) {
            return false;
        }

        $months = (($day->year - $anchor->year) * 12) + ($day->month - $anchor->month);

        return $months >= 0 && $months % 3 === 0;
    }
}
