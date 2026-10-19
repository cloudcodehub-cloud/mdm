<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase6BAdministrativeCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{dsp: Employee, supervisor: Employee, visit: Visit, scheduled: ScheduledVisit}
     */
    private function startedVisitForSupervisor(): array
    {
        Carbon::setTestNow('2026-10-20 09:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'latitude' => 40.1,
                'longitude' => -82.1,
                'accuracy' => 12,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
            ])
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();

        return compact('dsp', 'supervisor', 'visit', 'scheduled');
    }

    /**
     * @return array<string, mixed>
     */
    private function closePayload(string $endedAt = '2026-10-20T15:30'): array
    {
        return [
            'effective_ended_at' => $endedAt,
            'reason' => 'DSP device failed; Supervisor confirmed service ended.',
        ];
    }

    public function test_caseload_supervisor_can_administratively_close_active_visit(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'visit' => $visit] = $this->startedVisitForSupervisor();
        $notes = 'Morning ADLs completed before device failure.';
        $handover = 'Offer snack after lunch.';
        $visit->update([
            'visit_notes' => $notes,
            'handover_note' => $handover,
        ]);
        Carbon::setTestNow('2026-10-20 16:00:00');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.administrative-close', $visit), $this->closePayload())
            ->assertRedirect(route('visits.show', $visit));

        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertTrue($visit->isAdministrativelyClosed());
        $this->assertNull($visit->clocked_out_at);
        $this->assertNull($visit->clock_out_latitude);
        $this->assertNull($visit->clock_out_longitude);
        $this->assertNull($visit->clock_out_location_method);
        $this->assertNull($visit->clock_out_location_status);
        $this->assertSame('2026-10-20 15:30:00', $visit->administrative_effective_ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 16:00:00', $visit->administratively_closed_at?->format('Y-m-d H:i:s'));
        $this->assertSame($supervisor->user()->firstOrFail()->id, $visit->administratively_closed_by_user_id);
        $this->assertSame(
            'DSP device failed; Supervisor confirmed service ended.',
            $visit->administrative_close_reason,
        );
        $this->assertSame($notes, $visit->visit_notes);
        $this->assertSame($handover, $visit->handover_note);
        $this->assertSame(ScheduledVisitStatus::Completed, $visit->scheduledVisit->fresh()->status);
        $this->assertNull(
            Visit::query()->where('employee_id', $dsp->id)->inProgress()->first(),
        );

        $attendance = app(AttendanceStatusService::class);
        $this->assertSame(AttendanceStatus::Completed, $attendance->statusFor($visit->scheduledVisit->fresh()));
        $times = $attendance->effectiveTimes($visit->scheduledVisit->fresh());
        $this->assertSame('2026-10-20 09:00:00', $times['start']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 15:30:00', $times['end']?->format('Y-m-d H:i:s'));
        $this->assertSame(390, $attendance->workedMinutes($visit->scheduledVisit->fresh()));
    }

    public function test_admin_can_administratively_close_active_visit(): void
    {
        ['visit' => $visit] = $this->startedVisitForSupervisor();
        $admin = User::factory()->admin()->create();
        Carbon::setTestNow('2026-10-20 16:00:00');

        $this->actingAs($admin)
            ->post(route('visits.administrative-close', $visit), $this->closePayload())
            ->assertRedirect(route('visits.show', $visit));

        $this->assertTrue($visit->fresh()->isAdministrativelyClosed());
        $this->assertNull($visit->fresh()->clocked_out_at);
    }

    public function test_dsp_and_out_of_caseload_supervisor_cannot_administratively_close(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitForSupervisor();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        Carbon::setTestNow('2026-10-20 16:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.administrative-close', $visit), $this->closePayload())
            ->assertForbidden();

        $this->actingAs($otherSupervisor->user()->firstOrFail())
            ->post(route('visits.administrative-close', $visit), $this->closePayload())
            ->assertForbidden();

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertFalse($visit->fresh()->isAdministrativelyClosed());
    }

    public function test_duplicate_administrative_close_is_blocked(): void
    {
        ['supervisor' => $supervisor, 'visit' => $visit] = $this->startedVisitForSupervisor();
        Carbon::setTestNow('2026-10-20 16:00:00');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('visits.administrative-close', $visit), $this->closePayload())
            ->assertRedirect();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.administrative-close', $visit), $this->closePayload('2026-10-20T15:45'))
            ->assertForbidden();

        $this->assertSame(1, Visit::query()->whereNotNull('administratively_closed_at')->count());
        $this->assertSame(
            '2026-10-20 15:30:00',
            $visit->fresh()->administrative_effective_ended_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_dsp_clock_out_still_works_and_is_unchanged_by_admin_close_fields(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitForSupervisor();
        Carbon::setTestNow('2026-10-20 15:05:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), [
                'latitude' => 40.1111111,
                'longitude' => -82.2222222,
                'accuracy' => 8.25,
                'location_method' => ClockInLocationMethod::BrowserGps->value,
                'location_status' => ClockInLocationStatus::Captured->value,
                'handover_note' => 'Wrap-up complete for next DSP.',
            ])
            ->assertRedirect(route('visits.show', $visit));

        $visit->refresh();
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertSame('2026-10-20 15:05:00', $visit->clocked_out_at?->format('Y-m-d H:i:s'));
        $this->assertFalse($visit->isAdministrativelyClosed());
        $this->assertNull($visit->administrative_effective_ended_at);
        $this->assertSame(ClockInLocationStatus::Captured, $visit->clock_out_location_status);

        $times = app(AttendanceStatusService::class)->effectiveTimes($visit->scheduledVisit->fresh());
        $this->assertSame('2026-10-20 15:05:00', $times['end']?->format('Y-m-d H:i:s'));
    }

    public function test_effective_end_cannot_precede_clock_in_or_be_future(): void
    {
        ['supervisor' => $supervisor, 'visit' => $visit] = $this->startedVisitForSupervisor();
        Carbon::setTestNow('2026-10-20 16:00:00');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.administrative-close', $visit), $this->closePayload('2026-10-20T08:00'))
            ->assertRedirect(route('visits.show', $visit))
            ->assertSessionHasErrors('effective_ended_at');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.administrative-close', $visit), $this->closePayload('2026-10-20T17:00'))
            ->assertRedirect(route('visits.show', $visit))
            ->assertSessionHasErrors('effective_ended_at');

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
    }

    public function test_visit_show_exposes_administrative_close_capability_to_supervisor(): void
    {
        ['dsp' => $dsp, 'supervisor' => $supervisor, 'visit' => $visit] = $this->startedVisitForSupervisor();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.administratively_close', true)
                ->where('can.clock_out', false)
                ->where('visit.administratively_closed', false));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.administratively_close', false)
                ->where('can.clock_out', true));
    }
}
