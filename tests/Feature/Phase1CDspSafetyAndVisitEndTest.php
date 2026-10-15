<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitExceptionType;
use App\Enums\VisitSafetyCheckStatus;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitSafetyCheck;
use App\Services\ActiveVisitAttentionService;
use App\Services\SettingsService;
use App\Services\VisitSafetyCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1CDspSafetyAndVisitEndTest extends TestCase
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
        config([
            'mdm.visit.early_start_minutes' => 30,
            'mdm.safety_check.grace_minutes' => 2,
            'mdm.visit.overrun_reason_after_minutes' => 30,
            'mdm.visit.open_escalation_after_minutes' => 60,
        ]);
        Carbon::setTestNow('2026-09-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_timed_checks_use_grace_then_late_then_missed_with_supervisor_exception(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitWithInterval(5);

        Carbon::setTestNow('2026-09-11 10:05:00');
        $counts = app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $this->assertSame(1, $counts['created']);

        $check = VisitSafetyCheck::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertSame(VisitSafetyCheckStatus::Pending, $check->status);

        Carbon::setTestNow('2026-09-11 10:06:00'); // still in grace
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $this->assertSame(VisitSafetyCheckStatus::Pending, $check->fresh()->status);

        Carbon::setTestNow('2026-09-11 10:07:01'); // past grace
        $late = app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $this->assertSame(1, $late['late']);
        $this->assertSame(VisitSafetyCheckStatus::Late, $check->fresh()->status);
        $this->assertSame(1, InAppNotification::query()->where('title', 'Safety check overdue')->count());
        $this->assertSame(0, app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh())['notified']);

        Carbon::setTestNow('2026-09-11 10:10:00'); // next check due → first missed
        $missed = app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $this->assertGreaterThanOrEqual(1, $missed['missed']);
        $this->assertSame(VisitSafetyCheckStatus::Missed, $check->fresh()->status);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::MissedSafetyCheck)
                ->where('visit_safety_check_id', $check->id)
                ->exists(),
        );

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.safety-checks.acknowledge', [$visit, $check]), [
                'reason' => 'Client needed immediate support.',
            ])
            ->assertRedirect(route('visits.show', $visit));

        $this->assertSame('Client needed immediate support.', $check->fresh()->outcome_reason);
    }

    public function test_on_time_acknowledgment_and_late_reason_rules(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitWithInterval(10);

        Carbon::setTestNow('2026-09-11 10:10:00');
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $check = VisitSafetyCheck::query()->where('visit_id', $visit->id)->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.safety-checks.acknowledge', [$visit, $check]))
            ->assertRedirect();

        $this->assertSame(VisitSafetyCheckStatus::Completed, $check->fresh()->status);
        $this->assertNull($check->fresh()->outcome_reason);

        Carbon::setTestNow('2026-09-11 10:20:00');
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $second = VisitSafetyCheck::query()
            ->where('visit_id', $visit->id)
            ->where('sequence', 2)
            ->firstOrFail();

        Carbon::setTestNow('2026-09-11 10:23:00');
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.safety-checks.acknowledge', [$visit, $second]))
            ->assertSessionHasErrors('reason');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.safety-checks.acknowledge', [$visit, $second]), [
                'reason' => 'Was assisting with ADLs.',
            ])
            ->assertRedirect();

        $this->assertSame(VisitSafetyCheckStatus::Late, $second->fresh()->status);
        $this->assertNotNull($second->fresh()->completed_at);
    }

    public function test_wrap_up_past_end_and_forgotten_reminders_dedupe(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitWithInterval(null, [
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);

        $attention = app(ActiveVisitAttentionService::class);

        Carbon::setTestNow('2026-09-11 11:00:00');
        $first = $attention->dispatchDue();
        $this->assertSame(1, $first['wrap_up']);
        $this->assertSame(0, $first['past_end']); // at exact end, wrap-up only
        $this->assertSame(1, InAppNotification::query()->where('title', 'Wrap-up note still required')->count());
        $this->assertSame(0, $attention->dispatchDue()['wrap_up']);

        Carbon::setTestNow('2026-09-11 11:15:00');
        $past = $attention->dispatchDue();
        $this->assertSame(1, $past['past_end']);
        $this->assertSame(1, InAppNotification::query()->where('title', 'Visit running past scheduled end')->count());
        $this->assertSame(0, $attention->dispatchDue()['past_end']);

        Carbon::setTestNow('2026-09-11 12:00:00');
        $forgotten = $attention->dispatchDue();
        $this->assertSame(1, $forgotten['forgotten']);
        $this->assertSame(1, $forgotten['escalations']);
        $this->assertSame(
            1,
            InAppNotification::query()->where('title', 'Forgotten clock-out — visit still open')->count(),
        );
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::OpenVisitEscalation)
                ->exists(),
        );
        $this->assertSame(0, $attention->dispatchDue()['escalations']);
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);

        Artisan::call('mdm:dispatch-active-visit-monitors');
    }

    public function test_clock_out_requires_handover_extended_reason_and_safety_docs(): void
    {
        // Clock in at 10:00 into a visit that ends at 10:30, then overrun past +30.
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitWithInterval(15, [
            'starts_at' => '10:00:00',
            'ends_at' => '10:30:00',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.safety-confirmation', $visit))
            ->assertRedirect();

        // First timed check becomes due at 10:15 — complete on time before overrun.
        Carbon::setTestNow('2026-09-11 10:15:00');
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $check = VisitSafetyCheck::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.safety-checks.acknowledge', [$visit, $check]))
            ->assertRedirect();

        Carbon::setTestNow('2026-09-11 11:05:00'); // 35 min past scheduled end

        // Document any later checks that became Late/Missed during the overrun.
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        foreach (VisitSafetyCheck::query()->where('visit_id', $visit->id)->get() as $row) {
            if ($row->isDocumented()) {
                continue;
            }
            $this->actingAs($dsp->user()->firstOrFail())
                ->post(route('visits.safety-checks.acknowledge', [$visit, $row]), [
                    'reason' => 'Documented at wrap-up.',
                ])
                ->assertRedirect();
        }

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.clock-out', $visit), $this->clockOutGps())
            ->assertSessionHasErrors('handover_note');

        $this->actingAs($dsp->user()->firstOrFail())
            ->from(route('visits.show', $visit))
            ->post(route('visits.clock-out', $visit), [
                ...$this->clockOutGps(),
                'handover_note' => 'Client stable; continue evening routine.',
            ])
            ->assertSessionHasErrors('extended_visit_reason');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), [
                ...$this->clockOutGps(),
                'handover_note' => 'Client stable; continue evening routine.',
                'extended_visit_reason' => 'Transport delay after appointment.',
            ])
            ->assertRedirect();

        $this->assertSame(VisitStatus::Completed, $visit->fresh()->status);
        $this->assertSame('Transport delay after appointment.', $visit->fresh()->extended_visit_reason);
        $this->assertTrue(
            VisitException::query()
                ->where('visit_id', $visit->id)
                ->where('type', VisitExceptionType::ExtendedVisit)
                ->exists(),
        );
    }

    public function test_overnight_visit_attention_uses_agency_timezone(): void
    {
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        // 2026-03-07 is before US DST spring-forward; keep a simple overnight window.
        Carbon::setTestNow(Carbon::parse('2026-03-07 22:00:00', 'America/New_York'));

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        CarePlan::factory()->forClient($client)->create([
            'supervision_instruction' => 'Line of sight overnight.',
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-03-07',
            'starts_at' => '22:00:00',
            'ends_at' => '06:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->clockInGps())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame('Line of sight overnight.', $visit->supervision_instruction);

        Carbon::setTestNow(Carbon::parse('2026-03-08 06:30:00', 'America/New_York'));
        $this->assertTrue(app(ActiveVisitAttentionService::class)->requiresExtendedVisitReason($visit->fresh()));

        Carbon::setTestNow(Carbon::parse('2026-03-08 07:00:00', 'America/New_York'));
        $counts = app(ActiveVisitAttentionService::class)->dispatchDue();
        $this->assertSame(1, $counts['forgotten']);
        $this->assertSame(1, $counts['escalations']);
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.past_scheduled_end', true)
                ->where('visit.requires_extended_visit_reason', true)
                ->where('visit.supervision_instruction', 'Line of sight overnight.'));
    }

    public function test_supervisor_can_see_missed_safety_exception(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->startedVisitWithInterval(5);
        $supervisor = Employee::factory()->supervisor()->create();
        $visit->client->update(['supervisor_id' => $supervisor->id]);

        Carbon::setTestNow('2026-09-11 10:10:00');
        app(VisitSafetyCheckService::class)->evaluateVisit($visit->fresh());
        $check = VisitSafetyCheck::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertSame(VisitSafetyCheckStatus::Missed, $check->fresh()->status);

        $exception = VisitException::query()
            ->where('visit_id', $visit->id)
            ->where('type', VisitExceptionType::MissedSafetyCheck)
            ->firstOrFail();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('visit-exceptions.show', $exception))
            ->assertOk();
    }

    /**
     * @param  array{starts_at?: string, ends_at?: string}  $schedule
     * @return array{dsp: Employee, visit: Visit, scheduled: ScheduledVisit}
     */
    private function startedVisitWithInterval(?int $interval, array $schedule = []): array
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        CarePlan::factory()->forClient($client)->create([
            'timed_check_interval_minutes' => $interval,
            'supervision_instruction' => $interval === null ? 'Arm\'s length supervision.' : 'Checks on cadence.',
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => $schedule['starts_at'] ?? '09:00:00',
            'ends_at' => $schedule['ends_at'] ?? '17:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->clockInGps())
            ->assertRedirect();

        $visit = Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame($interval, $visit->safety_check_interval_minutes);

        return compact('dsp', 'visit', 'scheduled');
    }

    /**
     * @return array<string, mixed>
     */
    private function clockInGps(): array
    {
        return [
            'latitude' => 40.1234567,
            'longitude' => -82.9876543,
            'accuracy' => 12.5,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clockOutGps(): array
    {
        return [
            'latitude' => 40.1111111,
            'longitude' => -82.2222222,
            'accuracy' => 8.25,
            'location_method' => ClockInLocationMethod::BrowserGps->value,
            'location_status' => ClockInLocationStatus::Captured->value,
        ];
    }
}
