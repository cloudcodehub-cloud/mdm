<?php

namespace Tests\Feature;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\AttendanceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{supervisor: Employee, other: Employee, dsp: Employee, client: Client, scheduled: ScheduledVisit, visit: Visit}
     */
    private function scopedVisit(?string $serviceDate = '2026-09-11'): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => $serviceDate,
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 12:05:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 20:00:00', 'UTC'),
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
        ]);

        return compact('supervisor', 'other', 'dsp', 'client', 'scheduled', 'visit');
    }

    public function test_roles_see_only_permitted_attendance(): void
    {
        ['supervisor' => $supervisor, 'other' => $other, 'dsp' => $dsp, 'scheduled' => $scheduled] = $this->scopedVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('attendance.index', ['from' => '2026-09-11', 'to' => '2026-09-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/index')
                ->has('records.data', 1)
                ->where('records.data.0.id', $scheduled->id)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('attendance.index', ['from' => '2026-09-11', 'to' => '2026-09-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1));

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('attendance.index', ['from' => '2026-09-11', 'to' => '2026-09-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 0));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('attendance.index', ['from' => '2026-09-11', 'to' => '2026-09-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('records.data.0.employee.id', $dsp->id)
            );

        $outsider = Employee::factory()->dsp()->create();
        $this->actingAs($outsider->user()->firstOrFail())
            ->get(route('attendance.show', $scheduled))
            ->assertForbidden();
    }

    public function test_late_and_missed_use_operational_timezone(): void
    {
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        $dsp = Employee::factory()->dsp()->create();
        $late = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);
        $missed = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-09-10',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        Carbon::setTestNow('2026-09-11 12:30:00');

        $statuses = app(AttendanceStatusService::class);
        $this->assertSame(AttendanceStatus::Late, $statuses->statusFor($late->fresh()));
        $this->assertSame(AttendanceStatus::Missed, $statuses->statusFor($missed->fresh()));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('attendance.index', ['from' => '2026-09-10', 'to' => '2026-09-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
            );
    }

    public function test_worked_duration_uses_clock_times_until_adjustment(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->scopedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('attendance.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('record.status', AttendanceStatus::Completed->value)
                ->where('record.worked_duration', '7h 55m')
                ->where('record.is_adjusted', false)
                ->where('record.has_gps_issue', true)
            );

        $this->assertSame('2026-09-11 12:05:00', $visit->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_open_exception_marks_attendance_exception(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->scopedVisit();
        VisitException::factory()->create([
            'visit_id' => $visit->id,
            'type' => VisitExceptionType::GpsUnavailable,
            'status' => VisitExceptionStatus::Open,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('attendance.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('record.status', AttendanceStatus::Exception->value)
                ->where('record.has_exception', true)
            );
    }

    public function test_supervisor_requests_correction_and_cannot_approve(): void
    {
        ['supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->scopedVisit();
        $originalIn = $visit->clocked_in_at->copy();
        $originalOut = $visit->clocked_out_at->copy();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T08:00',
                'requested_clocked_out_at' => '2026-09-11T16:00',
                'reason' => 'DSP arrived on time; phone delayed clock-in.',
                'note' => 'Reviewed with client.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled));

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);
        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
        $this->assertTrue($originalOut->equalTo($visit->fresh()->clocked_out_at));

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Should not work',
            ])
            ->assertForbidden();

        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->fresh()->status);
        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
    }

    public function test_admin_approves_correction_without_changing_original_clocks(): void
    {
        ['supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit] = $this->scopedVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T08:00',
                'requested_clocked_out_at' => '2026-09-11T16:00',
                'reason' => 'Phone failed to clock in on arrival.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $originalIn = $visit->clocked_in_at->copy();

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Timesheet matches handover.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled));

        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
        $this->assertSame(AttendanceCorrectionStatus::Approved, $correction->fresh()->status);

        $this->actingAs($admin)
            ->get(route('attendance.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('record.status', AttendanceStatus::ManuallyAdjusted->value)
                ->where('record.is_adjusted', true)
                ->where('record.worked_duration', '8h 00m')
                ->where('record.original_duration', '7h 55m')
            );
    }

    public function test_admin_can_reject_and_apply_direct_correction(): void
    {
        ['scheduled' => $scheduled, 'visit' => $visit] = $this->scopedVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T07:00',
                'reason' => 'Admin timesheet correction.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled));

        $this->assertSame(
            '2026-09-11 12:05:00',
            $visit->fresh()->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s'),
        );
        $this->assertSame(AttendanceCorrectionStatus::Approved, $scheduled->fresh()->attendanceCorrections()->firstOrFail()->status);

        ['scheduled' => $second, 'visit' => $secondVisit, 'supervisor' => $supervisor] = $this->scopedVisit('2026-09-12');
        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $second), [
                'requested_clocked_out_at' => '2026-09-12T16:00',
                'reason' => 'Forgot to clock out.',
            ]);
        $pending = $second->fresh()->attendanceCorrections()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.reject', $pending), [
                'review_note' => 'Need more documentation.',
            ])
            ->assertRedirect();

        $this->assertSame(AttendanceCorrectionStatus::Rejected, $pending->fresh()->status);
        $this->assertNotNull($secondVisit->fresh()->clocked_out_at);
    }

    public function test_dsp_cannot_submit_correction(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled] = $this->scopedVisit();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T08:00',
                'reason' => 'Please fix.',
            ])
            ->assertForbidden();
    }
}
