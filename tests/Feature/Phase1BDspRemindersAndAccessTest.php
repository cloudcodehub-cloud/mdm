<?php

namespace Tests\Feature;

use App\Enums\DateFormat;
use App\Enums\DspClientAccessLevel;
use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Services\DspClientAccessService;
use App\Services\SettingsService;
use App\Services\ShiftReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1BDspRemindersAndAccessTest extends TestCase
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
        config(['mdm.visit.early_start_minutes' => 30]);
        Carbon::setTestNow('2026-09-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shift_reminders_fire_once_at_60_30_and_start_then_late_attention(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create(['first_name' => 'Elena', 'last_name' => 'Vasquez']);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '12:00:00',
            'ends_at' => '16:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $reminders = app(ShiftReminderService::class);

        Carbon::setTestNow('2026-09-11 10:30:00'); // >90 min early
        $this->assertSame(0, array_sum($reminders->dispatchDue()));

        Carbon::setTestNow('2026-09-11 11:00:00'); // 60 min before
        $first = $reminders->dispatchDue();
        $this->assertSame(1, $first['upcoming']);
        $this->assertSame(0, $first['ready']);
        $this->assertSame(1, InAppNotification::query()->where('title', 'Upcoming Shift')->count());

        $this->assertSame(0, $reminders->dispatchDue()['upcoming']); // dedupe

        Carbon::setTestNow('2026-09-11 11:30:00'); // 30 min before
        $ready = $reminders->dispatchDue();
        $this->assertSame(1, $ready['ready']);
        $this->assertSame(1, InAppNotification::query()->where('title', 'Shift Ready to Start')->count());
        $this->assertSame(0, $reminders->dispatchDue()['ready']);

        Carbon::setTestNow('2026-09-11 12:00:00');
        $start = $reminders->dispatchDue();
        $this->assertSame(1, $start['start_attention']);
        $attention = InAppNotification::query()
            ->where('source_key', 'like', 'schedule.visit.'.$visit->id.'.reminder.start_attention.%')
            ->firstOrFail();
        $this->assertSame('Shift has started', $attention->title);

        Carbon::setTestNow('2026-09-11 12:15:00');
        $late = $reminders->dispatchDue();
        $this->assertSame(1, $late['start_attention']);
        $this->assertSame('Late Start', $attention->fresh()->title);
        $this->assertSame(0, $reminders->dispatchDue()['start_attention']);

        $this->assertSame(3, InAppNotification::query()->where('user_id', $dsp->user()->firstOrFail()->id)->count());
    }

    public function test_cancelled_and_reassigned_visits_do_not_keep_sending_reminders(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $cancelled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '12:00:00',
            'ends_at' => '16:00:00',
            'status' => ScheduledVisitStatus::Cancelled,
        ]);
        $reassigned = ScheduledVisit::factory()->forClient($client)->forDsp($other)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '13:00:00',
            'ends_at' => '17:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        Carbon::setTestNow('2026-09-11 12:05:00');
        app(ShiftReminderService::class)->dispatchDue();

        $this->assertSame(0, InAppNotification::query()->where('user_id', $dsp->user()->firstOrFail()->id)->count());
        $this->assertGreaterThanOrEqual(
            1,
            InAppNotification::query()->where('user_id', $other->user()->firstOrFail()->id)->count(),
        );
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $dsp->user()->firstOrFail()->id,
            'title' => 'Late Start',
        ]);
        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $other->user()->firstOrFail()->id)
                ->where('source_key', 'like', 'schedule.visit.'.$reassigned->id.'.%')
                ->exists(),
        );
        $this->assertFalse(
            InAppNotification::query()
                ->where('source_key', 'like', 'schedule.visit.'.$cancelled->id.'.%')
                ->exists(),
        );
    }

    public function test_clocked_in_visit_stops_start_attention_reminders(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '12:00:00',
            'ends_at' => '16:00:00',
        ]);
        Visit::factory()->forScheduledVisit($scheduled)->create();

        Carbon::setTestNow('2026-09-11 12:20:00');
        $counts = app(ShiftReminderService::class)->dispatchDue();

        $this->assertSame(0, $counts['start_attention']);
        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_reminder_command_uses_america_new_york_operational_timezone(): void
    {
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Ultimate Care Supported Living',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '12:00:00',
            'ends_at' => '16:00:00',
        ]);

        // 11:00 America/New_York = 15:00 UTC on this date (EDT).
        Carbon::setTestNow(Carbon::parse('2026-09-11 11:00:00', 'America/New_York'));
        Artisan::call('mdm:dispatch-shift-reminders');

        $this->assertTrue(
            InAppNotification::query()
                ->where('type', InAppNotificationType::Schedule)
                ->where('title', 'Upcoming Shift')
                ->exists(),
        );
    }

    public function test_dsp_client_access_is_preparation_before_early_window_and_full_inside(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create([
            'address_line_1' => '12 Maple Ave',
            'city' => 'Lakeside',
            'state' => 'OH',
            'notes' => 'Confidential clinical note',
            'emergency_contact_name' => 'Parent Contact',
            'emergency_contact_phone' => '555-0100',
        ]);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '14:00:00',
            'ends_at' => '18:00:00',
            'notes' => 'Pharmacy pickup only',
        ]);

        $access = app(DspClientAccessService::class);
        $user = $dsp->user()->firstOrFail();

        Carbon::setTestNow('2026-09-11 13:00:00'); // 60 min early
        $this->assertSame(DspClientAccessLevel::Preparation, $access->levelFor($user, $client));

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('dsp_access.level', 'preparation')
                ->where('client.emergency_contact_name', null)
                ->where('client.notes', null)
                ->where('client.preparation.visit_instructions', 'Pharmacy pickup only')
                ->where('care_overview.history', [])
                ->where('carePlans', [])
            );

        Carbon::setTestNow('2026-09-11 13:30:00'); // early-start window
        $this->assertSame(DspClientAccessLevel::Full, $access->levelFor($user, $client));

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('dsp_access.level', 'full')
                ->where('client.emergency_contact_name', 'Parent Contact')
                ->where('client.notes', 'Confidential clinical note')
            );
    }

    public function test_cancelled_visit_revokes_full_access_and_unassigned_client_is_forbidden(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $assigned = Client::factory()->create();
        $other = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($assigned)->create();
        ScheduledVisit::factory()->forClient($assigned)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '14:00:00',
            'ends_at' => '18:00:00',
            'status' => ScheduledVisitStatus::Cancelled,
        ]);

        Carbon::setTestNow('2026-09-11 14:10:00');
        $user = $dsp->user()->firstOrFail();
        $this->assertSame(
            DspClientAccessLevel::Preparation,
            app(DspClientAccessService::class)->levelFor($user, $assigned),
        );

        $this->actingAs($user)->get(route('clients.show', $assigned))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dsp_access.level', 'preparation'));

        $this->actingAs($user)->get(route('clients.show', $other))->assertForbidden();
    }

    public function test_existing_clock_in_still_works_after_access_rules(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '10:00:00',
            'ends_at' => '14:00:00',
        ]);

        Carbon::setTestNow('2026-09-11 10:05:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'latitude' => 40.1,
                'longitude' => -82.9,
                'accuracy' => 12,
                'location_method' => 'browser_gps',
                'location_status' => 'captured',
            ])
            ->assertRedirect();

        $this->assertSame(1, Visit::query()->where('scheduled_visit_id', $scheduled->id)->count());
    }
}
