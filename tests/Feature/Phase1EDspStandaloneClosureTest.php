<?php

namespace Tests\Feature;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\DspClientAccessLevel;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1EDspStandaloneClosureTest extends TestCase
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
        Carbon::setTestNow('2026-09-11 18:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dsp_can_request_correction_from_own_completed_visit_preserving_original_clocks(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedOwnVisit();
        $originalIn = $visit->clocked_in_at->copy();
        $originalOut = $visit->clocked_out_at->copy();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('can.request_correction', true)
                ->where('attendance_url', route('attendance.show', $scheduled)));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('attendance.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.request_correction', true)
                ->where('can.review_corrections', false));

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T07:15',
                'requested_clocked_out_at' => '2026-09-11T15:05',
                'reason' => 'Phone delayed clock-in while already on site.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled));

        $correction = AttendanceCorrection::query()
            ->where('scheduled_visit_id', $scheduled->id)
            ->firstOrFail();

        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);
        $this->assertSame($dsp->user()->firstOrFail()->id, $correction->requested_by_user_id);
        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
        $this->assertTrue($originalOut->equalTo($visit->fresh()->clocked_out_at));
        $this->assertTrue($originalIn->equalTo($correction->original_clocked_in_at));
        $this->assertTrue($originalOut->equalTo($correction->original_clocked_out_at));
    }

    public function test_dsp_cannot_request_correction_for_other_dsp_or_in_progress_visit(): void
    {
        ['dsp' => $dsp, 'scheduled' => $own, 'visit' => $ownVisit] = $this->completedOwnVisit();
        $other = Employee::factory()->dsp()->create();
        $foreign = ScheduledVisit::factory()->forDsp($other)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);
        Visit::factory()->forScheduledVisit($foreign)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 07:05:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 15:00:00', 'UTC'),
            'clock_in_location_method' => ClockInLocationMethod::BrowserGps,
            'clock_in_location_status' => ClockInLocationStatus::Captured,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $foreign), [
                'requested_clocked_in_at' => '2026-09-11T07:00',
                'reason' => 'Not my visit.',
            ])
            ->assertForbidden();

        $ownVisit->forceFill([
            'status' => VisitStatus::InProgress,
            'clocked_out_at' => null,
        ])->save();
        $own->update(['status' => ScheduledVisitStatus::InProgress]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $own), [
                'requested_clocked_in_at' => '2026-09-11T07:00',
                'reason' => 'Still active.',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_and_admin_correction_permissions_remain_unchanged(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedOwnVisit();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp->update(['supervisor_id' => $supervisor->id]);
        $visit->client->update(['supervisor_id' => $supervisor->id]);
        $scheduled->update(['supervisor_id' => $supervisor->id]);
        $admin = User::factory()->admin()->create();
        $originalIn = $visit->clocked_in_at->copy();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T07:10',
                'requested_clocked_out_at' => '2026-09-11T15:00',
                'reason' => 'Supervisor-initiated correction.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Should stay admin-only.',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Approved.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled));

        $this->assertSame(AttendanceCorrectionStatus::Approved, $correction->fresh()->status);
        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
        $this->assertSame($dsp->id, $scheduled->fresh()->employee_id);
    }

    public function test_emergency_call_available_only_with_full_client_access(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create([
            'emergency_contact_name' => 'Jordan Lee',
            'emergency_contact_relationship' => 'Sister',
            'emergency_contact_phone' => '(614) 555-0199',
        ]);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        // Outside early-start window → preparation (no emergency phone payload).
        $future = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-12',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        Carbon::setTestNow('2026-09-11 10:00:00');
        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dsp_access.level', DspClientAccessLevel::Preparation->value)
                ->where('client.emergency_contact_phone', null));

        // Inside early-start window → full access exposes phone for Call.
        Carbon::setTestNow('2026-09-12 08:45:00');
        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dsp_access.level', DspClientAccessLevel::Full->value)
                ->where('client.emergency_contact_phone', '(614) 555-0199')
                ->where('client.emergency_contact_name', 'Jordan Lee'));

        $this->assertSame(ScheduledVisitStatus::Scheduled, $future->fresh()->status);
    }

    /**
     * @return array{dsp: Employee, client: Client, scheduled: ScheduledVisit, visit: Visit}
     */
    private function completedOwnVisit(): array
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 07:08:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 15:02:00', 'UTC'),
            'clock_in_location_method' => ClockInLocationMethod::BrowserGps,
            'clock_in_location_status' => ClockInLocationStatus::Captured,
            'handover_note' => 'Client settled after dinner.',
        ]);

        return compact('dsp', 'client', 'scheduled', 'visit');
    }
}
