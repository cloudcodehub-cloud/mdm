<?php

namespace Tests\Feature\Domain;

use App\Models\ShiftTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_and_evening_shifts_stay_on_the_same_calendar_day(): void
    {
        $day = ShiftTemplate::factory()->day()->create();
        $evening = ShiftTemplate::factory()->evening()->create();
        $serviceDate = Carbon::parse('2026-09-10');

        $this->assertFalse($day->spansOvernight());
        $this->assertFalse($evening->spansOvernight());
        $this->assertSame(480, $day->durationInMinutes());
        $this->assertSame(480, $evening->durationInMinutes());
        $this->assertSame('2026-09-10 07:00:00', $day->startsAtOn($serviceDate)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 15:00:00', $day->endsAtOn($serviceDate)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 15:00:00', $evening->startsAtOn($serviceDate)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 23:00:00', $evening->endsAtOn($serviceDate)->format('Y-m-d H:i:s'));
    }

    public function test_overnight_shifts_end_on_the_next_calendar_day(): void
    {
        $overnight = ShiftTemplate::factory()->overnight()->create();
        $serviceDate = Carbon::parse('2026-09-10');

        $this->assertTrue($overnight->spansOvernight());
        $this->assertSame(480, $overnight->durationInMinutes());
        $this->assertSame('2026-09-10 23:00:00', $overnight->startsAtOn($serviceDate)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 07:00:00', $overnight->endsAtOn($serviceDate)->format('Y-m-d H:i:s'));
    }

    public function test_a_24_hour_shift_is_treated_as_overnight(): void
    {
        $fullDay = ShiftTemplate::factory()->create([
            'name' => '24-hour',
            'code' => 'full_day',
            'starts_at' => '07:00:00',
            'ends_at' => '07:00:00',
        ]);

        $this->assertTrue($fullDay->spansOvernight());
        $this->assertSame(1440, $fullDay->durationInMinutes());
        $this->assertSame(
            '2026-09-11 07:00:00',
            $fullDay->endsAtOn(Carbon::parse('2026-09-10'))->format('Y-m-d H:i:s'),
        );
    }
}
