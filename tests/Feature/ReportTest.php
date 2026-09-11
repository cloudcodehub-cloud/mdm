<?php

namespace Tests\Feature;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\CredentialStatus;
use App\Enums\ReportType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
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
     * @return array{supervisor: Employee, other: Employee, dsp: Employee, outsider: Employee, client: Client, scheduled: ScheduledVisit, visit: Visit}
     */
    private function scopedVisit(?string $serviceDate = '2026-09-11'): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $outsider = Employee::factory()->dsp()->forSupervisor($other)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => $serviceDate,
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 12:05:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 20:00:00', 'UTC'),
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
        ]);

        return compact('supervisor', 'other', 'dsp', 'outsider', 'client', 'scheduled', 'visit');
    }

    public function test_admin_sees_reports_and_dsp_is_blocked(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled] = $this->scopedVisit();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->has('reports', count(ReportType::cases()))
            );

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::EmployeeAttendance->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/show')
                ->has('rows.data', 1)
                ->where('rows.data.0.employee', $scheduled->employee->full_name)
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('reports.show', ReportType::PayrollHours->value))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('reports.download', [
                'report' => ReportType::EmployeeAttendance->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertForbidden();
    }

    public function test_supervisor_reports_are_caseload_scoped(): void
    {
        ['supervisor' => $supervisor, 'other' => $other, 'dsp' => $dsp] = $this->scopedVisit();
        $otherClient = Client::factory()->forSupervisor($other)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($other)->create();
        ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create([
            'service_date' => '2026-09-11',
            'supervisor_id' => $other->id,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('reports.show', [
                'report' => ReportType::EmployeeAttendance->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.employee', $dsp->full_name)
                ->where('can.export', true)
            );

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('reports.show', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 0)
                ->where('can.export', false)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('reports.show', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.employee_number', $dsp->employee_number)
                ->where('can.export', false)
            );
    }

    public function test_report_filters_limit_rows(): void
    {
        ['dsp' => $dsp, 'client' => $client] = $this->scopedVisit();
        $otherDsp = Employee::factory()->dsp()->create();
        $otherClient = Client::factory()->create();
        $otherScheduled = ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create([
            'service_date' => '2026-09-11',
            'status' => ScheduledVisitStatus::Completed,
        ]);
        Visit::factory()->forScheduledVisit($otherScheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 20:00:00', 'UTC'),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::ClientVisits->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
                'employee_id' => (string) $dsp->id,
                'client_id' => (string) $client->id,
                'status' => VisitStatus::Completed->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.employee', $dsp->full_name)
                ->where('rows.data.0.client', $client->full_name)
            );
    }

    public function test_payroll_hours_use_effective_attendance_without_changing_clocks(): void
    {
        ['supervisor' => $supervisor, 'scheduled' => $scheduled, 'visit' => $visit, 'dsp' => $dsp] = $this->scopedVisit();
        $admin = User::factory()->admin()->create();
        $originalIn = $visit->clocked_in_at->copy();
        $originalOut = $visit->clocked_out_at->copy();

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.completed_visit_count', 1)
                ->where('rows.data.0.worked_hours', '7.92')
                ->where('summary.worked_hours', '7.92')
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-09-11T08:00',
                'requested_clocked_out_at' => '2026-09-11T16:00',
                'reason' => 'Phone failed to clock in on arrival.',
            ])
            ->assertRedirect();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Timesheet matches handover.',
            ])
            ->assertRedirect();

        $this->assertTrue($originalIn->equalTo($visit->fresh()->clocked_in_at));
        $this->assertTrue($originalOut->equalTo($visit->fresh()->clocked_out_at));
        $this->assertSame(AttendanceCorrectionStatus::Approved, $correction->fresh()->status);

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.employee_number', $dsp->employee_number)
                ->where('rows.data.0.completed_visit_count', 1)
                ->where('rows.data.0.worked_hours', '8.00')
            );

        $csv = $this->actingAs($admin)
            ->get(route('reports.download', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]));

        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Employee ID', $body);
        $this->assertStringContainsString($dsp->employee_number, $body);
        $this->assertStringContainsString('8.00', $body);
        $this->assertStringNotContainsString('hourly', strtolower($body));
        $this->assertStringNotContainsString('gross', strtolower($body));

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('reports.download', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertForbidden();
    }

    public function test_late_missed_task_compliance_and_exception_reports(): void
    {
        Carbon::setTestNow('2026-09-11 20:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $missed = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        $active = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'starts_at' => '15:00:00',
            'ends_at' => '23:00:00',
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($active)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-11 19:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-11 23:00:00', 'UTC'),
        ]);
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'status' => VisitTaskStatus::Completed,
            'title' => 'Assist with dinner',
        ]);
        VisitException::factory()->create([
            'visit_id' => $visit->id,
            'type' => VisitExceptionType::GpsUnavailable,
            'status' => VisitExceptionStatus::Open,
            'message' => 'GPS unavailable at clock-in.',
        ]);
        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'name' => 'CPR',
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-20',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::LateMissedVisits->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
                'status' => AttendanceStatus::Missed->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.status_label', 'Missed')
            );

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::TaskCompletion->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
                'status' => VisitTaskStatus::Completed->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.task', 'Assist with dinner')
            );

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::Exceptions->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::CredentialTrainingExpiration->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));

        $this->actingAs($admin)
            ->get(route('reports.show', ReportType::Compliance->value))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));

        $this->actingAs($admin)
            ->get(route('reports.download', [
                'report' => ReportType::TaskCompletion->value,
                'from' => '2026-09-11',
                'to' => '2026-09-11',
            ]))
            ->assertOk();

        $this->assertSame('scheduled', $missed->fresh()->status->value);
    }
}
