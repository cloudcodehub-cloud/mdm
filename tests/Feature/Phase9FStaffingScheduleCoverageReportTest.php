<?php

namespace Tests\Feature;

use App\Enums\DateFormat;
use App\Enums\ReportType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9FStaffingScheduleCoverageReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Coverage Reports',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-22 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{
     *     admin: User,
     *     supervisor: Employee,
     *     other: Employee,
     *     dsp: Employee,
     *     client: Client,
     *     assigned: ScheduledVisit,
     *     staffing: ScheduledVisit,
     *     closedGap: ScheduledVisit
     * }
     */
    private function world(): array
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();

        $assigned = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-22',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
            'staffing_needed' => false,
            'service_type' => 'Residential Habilitation',
        ]);

        $staffing = ScheduledVisit::factory()->forClient($client)->create([
            'employee_id' => null,
            'service_date' => '2026-10-22',
            'starts_at' => '10:00:00',
            'ends_at' => '14:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
            'staffing_needed' => true,
            'released_employee_id' => $dsp->id,
            'attention_reason' => 'Staffing Needed: DSP unavailable',
            'service_type' => 'Community Habilitation',
        ]);

        $closedGap = ScheduledVisit::factory()->forClient($client)->create([
            'employee_id' => null,
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00',
            'ends_at' => '12:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Cancelled,
            'staffing_needed' => true,
            'attention_reason' => 'Cancelled while Staffing Needed',
            'service_type' => 'Respite',
        ]);

        // Outside supervisor caseload — Admin sees, Supervisor does not.
        $otherClient = Client::factory()->forSupervisor($other)->create();
        ScheduledVisit::factory()->forClient($otherClient)->create([
            'employee_id' => null,
            'service_date' => '2026-10-22',
            'supervisor_id' => $other->id,
            'status' => ScheduledVisitStatus::Scheduled,
            'staffing_needed' => true,
        ]);

        return compact('admin', 'supervisor', 'other', 'dsp', 'client', 'assigned', 'staffing', 'closedGap');
    }

    public function test_admin_report_lists_staffing_and_assigned_coverage_with_filters(): void
    {
        ['admin' => $admin, 'assigned' => $assigned, 'staffing' => $staffing, 'closedGap' => $closedGap] = $this->world();

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->has('reports', count(ReportType::cases()))
                ->where(
                    'reports',
                    fn (mixed $reports): bool => collect($reports)->contains(
                        fn (mixed $report): bool => is_array($report)
                            && ($report['key'] ?? null) === ReportType::StaffingScheduleCoverage->value,
                    ),
                ));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-21',
                'to' => '2026-10-22',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/show')
                ->where('report.key', ReportType::StaffingScheduleCoverage->value)
                ->where('timezone', 'America/New_York')
                ->where('can.export', true)
                ->where('rows.meta.total', 4)
                ->where('rows.data', fn (mixed $rows): bool => collect($rows)->contains(
                    fn (mixed $row): bool => is_array($row)
                        && ($row['coverage'] ?? null) === 'assigned'
                        && ($row['employee'] ?? null) !== 'Staffing Needed',
                ) && collect($rows)->contains(
                    fn (mixed $row): bool => is_array($row)
                        && ($row['coverage'] ?? null) === 'unresolved'
                        && ($row['coverage_label'] ?? null) === 'Unresolved Staffing Needed',
                ) && collect($rows)->contains(
                    fn (mixed $row): bool => is_array($row)
                        && ($row['coverage'] ?? null) === 'staffing_needed'
                        && ($row['schedule_status_label'] ?? null) === 'Cancelled',
                )));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-21',
                'to' => '2026-10-22',
                'status' => 'unresolved',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 2)
                ->where('rows.data.0.coverage', 'unresolved'));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-21',
                'to' => '2026-10-22',
                'status' => 'assigned',
                'client_id' => (string) $assigned->client_id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 1)
                ->where('rows.data.0.coverage', 'assigned')
                ->where('rows.data.0.schedule_status_label', 'Scheduled'));

        $csv = $this->actingAs($admin)
            ->get(route('reports.download', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-21',
                'to' => '2026-10-22',
                'status' => 'staffing_needed',
            ]));

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Coverage', $body);
        $this->assertStringContainsString('Unresolved Staffing Needed', $body);
        $this->assertStringContainsString($staffing->attention_reason, $body);
        $this->assertStringContainsString($closedGap->attention_reason, $body);
        $this->assertStringNotContainsString('Residential Habilitation', $body);
    }

    public function test_supervisor_is_caseload_scoped_and_dsp_cannot_access(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp] = $this->world();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('reports.show', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-21',
                'to' => '2026-10-22',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.meta.total', 3)
                ->where('can.export', true));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('reports.show', ReportType::StaffingScheduleCoverage->value))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('reports.download', [
                'report' => ReportType::StaffingScheduleCoverage->value,
                'from' => '2026-10-22',
                'to' => '2026-10-22',
            ]))
            ->assertForbidden();
    }

    public function test_existing_attendance_report_family_unchanged(): void
    {
        ['admin' => $admin, 'assigned' => $assigned] = $this->world();
        $assigned->update(['status' => ScheduledVisitStatus::Completed]);

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::EmployeeAttendance->value,
                'from' => '2026-10-22',
                'to' => '2026-10-22',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/show')
                ->where('report.key', ReportType::EmployeeAttendance->value));
    }
}
