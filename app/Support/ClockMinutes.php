<?php

namespace App\Support;

final class ClockMinutes
{
    public static function fromTime(?string $time): ?int
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $normalized = self::normalize($time);
        $parts = explode(':', $normalized);

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }

    public static function normalize(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return substr($time, 0, 8);
    }

    public static function toLabel(int $minutes): string
    {
        $wrapped = $minutes % 1440;
        $hours = intdiv($wrapped, 60);
        $mins = $wrapped % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * @return array{start: int, end: int}
     */
    public static function window(string $start, string $end): array
    {
        $startMin = self::fromTime($start) ?? 0;
        $endMin = self::fromTime($end) ?? 0;

        if ($endMin <= $startMin) {
            $endMin += 1440;
        }

        return ['start' => $startMin, 'end' => $endMin];
    }
}
