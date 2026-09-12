<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class WeekBoundary
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function containing(Carbon $day, int $firstDayOfWeek): array
    {
        $day = $day->copy()->startOfDay();
        $first = self::normalize($firstDayOfWeek);
        $delta = ($day->dayOfWeek - $first + 7) % 7;
        $start = $day->copy()->subDays($delta);
        $end = $start->copy()->addDays(6);

        return [$start, $end];
    }

    /**
     * @return list<string>
     */
    public static function dates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    public static function shift(string $view, Carbon $anchor, int $direction, int $firstDayOfWeek): Carbon
    {
        $anchor = $anchor->copy()->startOfDay();

        return match ($view) {
            'day' => $anchor->addDays($direction),
            'month' => $anchor->addMonths($direction)->startOfMonth(),
            default => self::containing($anchor, $firstDayOfWeek)[0]->addWeeks($direction),
        };
    }

    /**
     * @return list<string>
     */
    public static function weekdayLabels(int $firstDayOfWeek): array
    {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $first = self::normalize($firstDayOfWeek);

        return [...array_slice($labels, $first), ...array_slice($labels, 0, $first)];
    }

    public static function leadingBlanks(Carbon $monthStart, int $firstDayOfWeek): int
    {
        $first = self::normalize($firstDayOfWeek);

        return ($monthStart->copy()->startOfMonth()->dayOfWeek - $first + 7) % 7;
    }

    public static function normalize(int $firstDayOfWeek): int
    {
        return max(0, min(6, $firstDayOfWeek));
    }
}
