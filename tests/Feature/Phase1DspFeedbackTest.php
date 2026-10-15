<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\TimeFormat;
use App\Enums\VisitExceptionType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1DspFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
        Carbon::setTestNow('2026-09-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function gpsPayload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => 40.1234567,
            'longitude' => -82.9876543,
            'accuracy' => 12.5,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailablePayload(array $overrides = []): array
    {
        return array_merge([
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
        ], $overrides);
    }

    public function test_dsp_dashboard_prioritizes_current_or_next_shift_and_later_today(): void
    {
        $supervisor = Employee::factory()->supervisor()->create(['first_name' => 'Jordan', 'last_name' => 'Hale']);
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $morningClient = Client::factory()->create([
            'first_name' => 'Elena',
            'last_name' => 'Vasquez',
            'address_line_1' => '12 Maple Ave',
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44004',
            'supervisor_id' => $supervisor->id,
        ]);
        $afternoonClient = Client::factory()->create([
            'first_name' => 'Theo',
            'last_name' => 'Nguyen',
            'supervisor_id' => $supervisor->id,
        ]);
        $futureClient = Client::factory()->create([
            'first_name' => 'Harper',
            'last_name' => 'Ellis',
            'supervisor_id' => $supervisor->id,
        ]);

        $morning = ScheduledVisit::factory()->forClient($morningClient)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'service_type' => 'Residential Habilitation',
            'supervisor_id' => $supervisor->id,
        ]);
        $afternoon = ScheduledVisit::factory()->forClient($afternoonClient)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '14:00:00',
            'ends_at' => '18:00:00',
            'service_type' => 'Personal Care',
            'supervisor_id' => $supervisor->id,
        ]);
        $future = ScheduledVisit::factory()->forClient($futureClient)->forDsp($dsp)->create([
            'service_date' => '2026-09-12',
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Community Support',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.clock_in_visit.id', $morning->id)
                ->where('dashboard.clock_in_visit.client.name', $morningClient->full_name)
                ->where('dashboard.clock_in_visit.service_type', 'Residential Habilitation')
                ->where('dashboard.clock_in_visit.supervisor_name', $supervisor->full_name)
                ->where('dashboard.clock_in_visit.can_start', true)
                ->where('dashboard.later_today_visits.0.id', $afternoon->id)
                ->where('dashboard.next_future_visit.id', $future->id)
                ->where('dashboard.work_items', fn ($items) => collect($items)
                    ->where('action_label', 'Start Visit')
                    ->isEmpty())
            );
    }

    public function test_clock_in_windows_honor_america_new_york_operational_timezone(): void
    {
        config(['mdm.visit.early_start_minutes' => 30]);
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Ultimate Care Supported Living',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        $settings = app(SettingsService::class);
        $this->assertSame('America/New_York', $settings->timezone());

        $tooEarlyDsp = Employee::factory()->dsp()->create();
        $earlyDsp = Employee::factory()->dsp()->create();
        $onTimeDsp = Employee::factory()->dsp()->create();
        $lateDsp = Employee::factory()->dsp()->create();

        $tooEarly = ScheduledVisit::factory()->forDsp($tooEarlyDsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);
        $early = ScheduledVisit::factory()->forDsp($earlyDsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);
        $onTime = ScheduledVisit::factory()->forDsp($onTimeDsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);
        $late = ScheduledVisit::factory()->forDsp($lateDsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:20:00', 'America/New_York'));
        $this->assertFalse($tooEarly->fresh()->isEligibleToStart());
        $this->actingAs($tooEarlyDsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $tooEarly), $this->gpsPayload())
            ->assertSessionHasErrors('scheduled_visit');

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:35:00', 'America/New_York'));
        $this->assertTrue($early->fresh()->isEligibleToStart());
        $this->assertTrue($early->fresh()->isEarlyStart());
        $this->actingAs($earlyDsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $early), $this->gpsPayload([
                'early_start_confirmed' => true,
            ]))
            ->assertRedirect();
        $earlyVisit = Visit::query()->where('scheduled_visit_id', $early->id)->firstOrFail();
        $this->assertTrue($earlyVisit->clocked_in_at->eq(Carbon::parse('2026-09-11 08:35:00', 'America/New_York')));
        $this->assertFalse(
            VisitException::query()
                ->where('visit_id', $earlyVisit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );

        Carbon::setTestNow(Carbon::parse('2026-09-11 09:00:00', 'America/New_York'));
        $this->assertTrue($onTime->fresh()->isEligibleToStart());
        $this->assertFalse($onTime->fresh()->isEarlyStart());
        $this->actingAs($onTimeDsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $onTime), $this->gpsPayload())
            ->assertRedirect();
        $onTimeVisit = Visit::query()->where('scheduled_visit_id', $onTime->id)->firstOrFail();
        $this->assertTrue($onTimeVisit->clocked_in_at->eq(Carbon::parse('2026-09-11 09:00:00', 'America/New_York')));
        $this->assertFalse(
            VisitException::query()
                ->where('visit_id', $onTimeVisit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );

        Carbon::setTestNow(Carbon::parse('2026-09-11 09:20:00', 'America/New_York'));
        $this->assertTrue($late->fresh()->isEligibleToStart());
        $this->assertTrue($late->fresh()->isLateRelativeToStart());
        $this->actingAs($lateDsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $late), $this->gpsPayload())
            ->assertRedirect();
        $lateVisit = Visit::query()->where('scheduled_visit_id', $late->id)->firstOrFail();
        $this->assertTrue($lateVisit->clocked_in_at->eq(Carbon::parse('2026-09-11 09:20:00', 'America/New_York')));
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $lateVisit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );
    }

    public function test_visit_cannot_start_earlier_than_configured_window(): void
    {
        config(['mdm.visit.early_start_minutes' => 30]);
        Carbon::setTestNow('2026-09-11 08:20:00');

        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);

        $this->assertFalse($scheduled->isEligibleToStart());

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('dashboard'))
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->gpsPayload())
            ->assertSessionHasErrors('scheduled_visit');

        $this->assertSame(0, Visit::query()->count());
    }

    public function test_early_start_within_window_requires_confirmation_and_keeps_actual_timestamp(): void
    {
        config(['mdm.visit.early_start_minutes' => 30]);
        Carbon::setTestNow('2026-09-11 08:45:00');

        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);

        $this->assertTrue($scheduled->isEligibleToStart());
        $this->assertTrue($scheduled->isEarlyStart());

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('dashboard'))
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->gpsPayload())
            ->assertSessionHasErrors('early_start_confirmed');

        $this->assertSame(0, Visit::query()->count());

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->gpsPayload([
                'early_start_confirmed' => true,
            ]))
            ->assertRedirect();

        $visit = Visit::query()->firstOrFail();
        $this->assertSame('2026-09-11 08:45:00', $visit->clocked_in_at->format('Y-m-d H:i:s'));
        $this->assertFalse(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );
    }

    public function test_normal_scheduled_clock_in_and_late_start_flag(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $onTime = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '10:00:00',
            'ends_at' => '14:00:00',
        ]);

        Carbon::setTestNow('2026-09-11 10:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $onTime), $this->gpsPayload())
            ->assertRedirect();

        $onTimeVisit = Visit::query()->where('scheduled_visit_id', $onTime->id)->firstOrFail();
        $this->assertSame('2026-09-11 10:00:00', $onTimeVisit->clocked_in_at->format('Y-m-d H:i:s'));
        $this->assertFalse(
            VisitException::query()
                ->where('visit_id', $onTimeVisit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $onTimeVisit), [
                'latitude' => 40.1,
                'longitude' => -82.9,
                'accuracy' => 10,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
                'acknowledge_unfinished_required' => false,
            ])
            ->assertRedirect();

        Carbon::setTestNow('2026-09-11 15:30:00');
        $late = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '15:00:00',
            'ends_at' => '19:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $late), $this->gpsPayload())
            ->assertRedirect();

        $lateVisit = Visit::query()->where('scheduled_visit_id', $late->id)->firstOrFail();
        $this->assertSame('2026-09-11 15:30:00', $lateVisit->clocked_in_at->format('Y-m-d H:i:s'));
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $lateVisit->id)
                ->where('type', VisitExceptionType::LateStart)
                ->exists(),
        );
    }

    public function test_missed_shift_does_not_create_worked_time(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $missed = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        Carbon::setTestNow('2026-09-11 10:00:00');

        $statuses = app(AttendanceStatusService::class);
        $this->assertSame(AttendanceStatus::Missed, $statuses->statusFor($missed->fresh()));
        $this->assertNull($statuses->workedMinutes($missed->fresh()));
        $this->assertSame(0, Visit::query()->where('scheduled_visit_id', $missed->id)->count());
    }

    public function test_successful_gps_clock_in_and_no_gps_fallback_rules(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $withGps = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $withGps), $this->gpsPayload())
            ->assertRedirect();

        $gpsVisit = Visit::query()->where('scheduled_visit_id', $withGps->id)->firstOrFail();
        $this->assertEqualsWithDelta(40.1234567, (float) $gpsVisit->clock_in_latitude, 0.0001);
        $this->assertSame(ClockInLocationStatus::Captured, $gpsVisit->clock_in_location_status);
        $this->assertNull($gpsVisit->clock_in_unavailable_reason);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $gpsVisit), [
                'latitude' => 40.1,
                'longitude' => -82.9,
                'accuracy' => 10,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
                'acknowledge_unfinished_required' => false,
            ])
            ->assertRedirect();

        $fallback = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '13:00:00',
            'ends_at' => '17:00:00',
        ]);
        Carbon::setTestNow('2026-09-11 13:10:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('dashboard'))
            ->post(route('scheduled-visits.clock-in', $fallback), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
            ])
            ->assertSessionHasErrors('gps_fallback_reason');

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('dashboard'))
            ->post(route('scheduled-visits.clock-in', $fallback), $this->unavailablePayload([
                'gps_fallback_reason' => GpsFallbackReason::Other->value,
            ]))
            ->assertSessionHasErrors('gps_fallback_note');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $fallback), $this->unavailablePayload([
                'gps_fallback_reason' => GpsFallbackReason::GpsUnavailable->value,
            ]))
            ->assertRedirect();

        $fallbackVisit = Visit::query()->where('scheduled_visit_id', $fallback->id)->firstOrFail();
        $this->assertNull($fallbackVisit->clock_in_latitude);
        $this->assertSame(GpsFallbackReason::GpsUnavailable->label(), $fallbackVisit->clock_in_unavailable_reason);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $fallbackVisit->id)
                ->where('type', VisitExceptionType::GpsUnavailable)
                ->exists(),
        );

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $fallbackVisit), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
                'unavailable_reason' => 'Still no GPS at clock-out.',
                'acknowledge_unfinished_required' => false,
            ])
            ->assertRedirect();

        $other = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '18:00:00',
            'ends_at' => '21:00:00',
        ]);
        Carbon::setTestNow('2026-09-11 18:05:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $other), $this->unavailablePayload([
                'gps_fallback_reason' => GpsFallbackReason::Other->value,
                'gps_fallback_note' => 'Phone location services crashed.',
            ]))
            ->assertRedirect();

        $otherVisit = Visit::query()->where('scheduled_visit_id', $other->id)->firstOrFail();
        $this->assertSame('Phone location services crashed.', $otherVisit->clock_in_unavailable_reason);
    }

    public function test_dsp_cannot_clock_in_another_dsps_visit(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($other)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '09:00:00',
            'ends_at' => '15:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->gpsPayload())
            ->assertForbidden();

        $this->assertSame(0, Visit::query()->count());
    }
}
