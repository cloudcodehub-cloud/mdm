<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\VisitClockInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimezoneOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function setOrganizationTimezone(string $timezone, array $overrides = []): SettingsService
    {
        $service = app(SettingsService::class);
        $service->updateOrganization(array_merge([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => $timezone,
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ], $overrides));

        return $service;
    }

    public function test_timezone_change_moves_operational_today_without_rewriting_timestamps(): void
    {
        $settings = $this->setOrganizationTimezone('UTC');
        Carbon::setTestNow('2026-09-11 02:00:00');

        $this->assertSame('2026-09-11', $settings->today());

        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        $this->assertTrue($scheduled->isEligibleToStart());

        $visit = app(VisitClockInService::class)->clockIn(
            $dsp->user()->firstOrFail(),
            $scheduled,
            [
                'location_method' => 'gps_unavailable',
                'location_status' => 'denied',
                'unavailable_reason' => 'Timezone persistence check.',
            ],
        );

        $clockedInAt = $visit->clocked_in_at->copy();
        $this->assertSame('2026-09-11 02:00:00', $clockedInAt->timezone('UTC')->format('Y-m-d H:i:s'));

        $this->setOrganizationTimezone('America/New_York');

        $this->assertSame('2026-09-10', app(SettingsService::class)->today());
        $this->assertSame(
            '2026-09-11 02:00:00',
            $visit->fresh()->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s'),
        );
        $this->assertTrue($clockedInAt->eq($visit->fresh()->clocked_in_at));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('dashboard.today', '2026-09-10'));
    }

    public function test_overnight_shift_stays_eligible_across_the_operational_midnight_boundary(): void
    {
        $this->setOrganizationTimezone('America/New_York');
        $overnight = ShiftTemplate::factory()->overnight()->create();
        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
            'shift_template_id' => $overnight->id,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertSame('2026-09-10 23:00:00', $scheduled->startsAtOn()->timezone('America/New_York')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 07:00:00', $scheduled->endsAtOn()->timezone('America/New_York')->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-11 03:30:00');
        $this->assertSame('2026-09-10', app(SettingsService::class)->today());
        $this->assertTrue($scheduled->isEligibleToStart());

        Carbon::setTestNow('2026-09-11 04:30:00');
        $this->assertSame('2026-09-11', app(SettingsService::class)->today());
        $this->assertTrue($scheduled->fresh()->isEligibleToStart());

        Carbon::setTestNow('2026-09-11 12:00:00');
        $this->assertFalse($scheduled->fresh()->isEligibleToStart());
    }

    public function test_same_day_shift_windows_are_interpreted_in_the_operational_timezone(): void
    {
        $this->setOrganizationTimezone('America/Chicago');
        $day = ShiftTemplate::factory()->day()->create();
        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
            'shift_template_id' => $day->id,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $start = $scheduled->startsAtOn();
        $this->assertSame('America/Chicago', $start->timezoneName);
        $this->assertSame('2026-09-10 07:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 12:00:00', $start->timezone('UTC')->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-10 13:00:00');
        $this->assertTrue($scheduled->isEligibleToStart());
    }
}
