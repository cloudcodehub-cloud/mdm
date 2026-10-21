<?php

namespace Tests\Unit;

use App\Support\TimezoneOptionSearch;
use PHPUnit\Framework\TestCase;

class TimezoneOptionSearchTest extends TestCase
{
    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    private function options(): array
    {
        return [
            ['value' => 'America/New_York', 'label' => 'Eastern Time (EST/EDT)', 'group' => 'United States'],
            ['value' => 'America/Chicago', 'label' => 'Central Time (CST/CDT)', 'group' => 'United States'],
            ['value' => 'Europe/London', 'label' => 'London (GMT)', 'group' => 'All timezones'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo (JST)', 'group' => 'All timezones'],
        ];
    }

    public function test_empty_query_returns_all_options(): void
    {
        $this->assertCount(4, TimezoneOptionSearch::filter($this->options(), ''));
        $this->assertCount(4, TimezoneOptionSearch::filter($this->options(), '   '));
    }

    public function test_search_matches_city_region_label_and_iana_identifier(): void
    {
        $options = $this->options();

        $this->assertSame(
            ['America/Chicago'],
            array_column(TimezoneOptionSearch::filter($options, 'chicago'), 'value'),
        );
        $this->assertSame(
            ['America/New_York', 'America/Chicago'],
            array_column(TimezoneOptionSearch::filter($options, 'america'), 'value'),
        );
        $this->assertSame(
            ['America/New_York'],
            array_column(TimezoneOptionSearch::filter($options, 'eastern'), 'value'),
        );
        $this->assertSame(
            ['Europe/London'],
            array_column(TimezoneOptionSearch::filter($options, 'Europe/London'), 'value'),
        );
        $this->assertSame(
            ['Asia/Tokyo'],
            array_column(TimezoneOptionSearch::filter($options, 'tokyo'), 'value'),
        );
        $this->assertSame(
            [],
            TimezoneOptionSearch::filter($options, 'not-a-real-zone'),
        );
    }
}
