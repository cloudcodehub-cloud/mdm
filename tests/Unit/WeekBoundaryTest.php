<?php

namespace Tests\Unit;

use App\Support\WeekBoundary;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeekBoundaryTest extends TestCase
{
    public function test_sunday_first_week_contains_tuesday_in_that_sunday_saturday_span(): void
    {
        [$start, $end] = WeekBoundary::containing(Carbon::parse('2026-09-15'), 0);

        $this->assertSame('2026-09-13', $start->toDateString());
        $this->assertSame('2026-09-19', $end->toDateString());
        $this->assertSame(
            ['2026-09-13', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19'],
            WeekBoundary::dates($start, $end),
        );
    }

    public function test_monday_first_week_does_not_use_carbon_locale_weekends(): void
    {
        [$start, $end] = WeekBoundary::containing(Carbon::parse('2026-09-15'), 1);

        $this->assertSame('2026-09-14', $start->toDateString());
        $this->assertSame('2026-09-20', $end->toDateString());
        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], WeekBoundary::weekdayLabels(1));
        $this->assertSame(1, WeekBoundary::leadingBlanks(Carbon::parse('2026-09-01'), 1));
    }

    public function test_week_navigation_moves_by_the_same_boundary(): void
    {
        $anchor = Carbon::parse('2026-09-16');
        $previous = WeekBoundary::shift('week', $anchor, -1, 0);
        $next = WeekBoundary::shift('week', $anchor, 1, 0);

        $this->assertSame('2026-09-06', $previous->toDateString());
        $this->assertSame('2026-09-20', $next->toDateString());
    }
}
