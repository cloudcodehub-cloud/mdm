<?php

namespace Tests\Feature;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DateFormat;
use App\Enums\InAppNotificationType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\ReportType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\PayrollPeriod;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitOutcomeReclassification;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase7APayrollFinalizationAndEvvClosureTest extends TestCase
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
     * @return array{admin: User, supervisor: Employee, dsp: Employee, scheduled: ScheduledVisit, visit: Visit}
     */
    private function completedFixture(): array
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-15',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-15 09:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-10-15 17:00:00', 'UTC'),
        ]);

        return compact('admin', 'supervisor', 'dsp', 'scheduled', 'visit');
    }

    public function test_admin_finalizes_reopens_and_refinalizes_payroll_period(): void
    {
        ['admin' => $admin, 'scheduled' => $scheduled] = $this->completedFixture();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.finalize'), [
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
                'scope' => 'period',
                'confirm' => '1',
                'finalize_note' => 'October close.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $period = PayrollPeriod::query()->firstOrFail();
        $this->assertSame(PayrollPeriodStatus::Finalized, $period->status);
        $this->assertTrue($period->isExportReady());
        $this->assertSame(1, $period->audits()->count());

        $this->actingAs($admin)
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-15T09:00',
                'requested_clocked_out_at' => '2026-10-15T16:00',
                'reason' => 'Should be blocked while finalized.',
            ])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.reopen', $period), [
                'reopen_reason' => 'Need to correct one DSP timesheet.',
                'confirm' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame(PayrollPeriodStatus::ReopenedForCorrection, $period->status);
        $this->assertFalse($period->isExportReady());

        $this->actingAs($admin)
            ->get(route('reports.download', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('attendance.corrections.store', $scheduled), [
                'requested_clocked_in_at' => '2026-10-15T09:00',
                'requested_clocked_out_at' => '2026-10-15T16:30',
                'reason' => 'Allowed while reopened.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            AttendanceCorrectionStatus::Approved,
            $scheduled->fresh()->attendanceCorrections()->firstOrFail()->status,
        );

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.re-finalize', $period), [
                'confirm' => '1',
                'finalize_note' => 'Corrections applied.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame(PayrollPeriodStatus::Finalized, $period->status);
        $this->assertTrue($period->isExportReady());
        $this->assertGreaterThanOrEqual(3, $period->audits()->count());
    }

    public function test_supervisor_cannot_reopen_finalized_payroll(): void
    {
        ['admin' => $admin, 'supervisor' => $supervisor] = $this->completedFixture();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.finalize'), [
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
                'scope' => 'period',
                'confirm' => '1',
            ])
            ->assertSessionHasNoErrors();

        $period = PayrollPeriod::query()->firstOrFail();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.reopen', $period), [
                'reopen_reason' => 'Supervisor attempt.',
                'confirm' => '1',
            ])
            ->assertForbidden();
    }

    public function test_pending_correction_blocks_finalization(): void
    {
        ['admin' => $admin, 'dsp' => $dsp, 'scheduled' => $scheduled, 'visit' => $visit] = $this->completedFixture();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'visit_id' => $visit->id,
                'requested_clocked_in_at' => '2026-10-15T09:05',
                'requested_clocked_out_at' => '2026-10-15T17:00',
                'reason' => 'Pending hour change.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payroll-periods.finalize'), [
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
                'scope' => 'period',
                'confirm' => '1',
            ])
            ->assertSessionHasErrors('period_start');
    }

    public function test_missed_to_cancelled_preserves_history_and_notifies_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-18',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->assertSame(
            AttendanceStatus::Missed,
            app(\App\Services\AttendanceStatusService::class)->statusFor($scheduled),
        );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.reclassify-missed-cancelled', $scheduled), [
                'reason' => 'Client cancelled before arrival; verified with family.',
                'evidence_note' => 'Phone log 10/18 8:40am.',
            ])
            ->assertRedirect(route('attendance.show', $scheduled))
            ->assertSessionHasNoErrors();

        $scheduled->refresh();
        $this->assertSame(ScheduledVisitStatus::Cancelled, $scheduled->status);
        $this->assertNotNull($scheduled->cancelled_at);
        $this->assertSame(0, $scheduled->visits()->count());

        $history = VisitOutcomeReclassification::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
        $this->assertSame(AttendanceStatus::Missed->value, $history->from_outcome);
        $this->assertSame(ScheduledVisitStatus::Cancelled->value, $history->to_outcome);

        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', InAppNotificationType::MaterialCorrection)
                ->where('source_key', 'attendance.reclassification.'.$history->id.'.missed-cancelled.admin.'.$admin->id)
                ->exists(),
        );

        // Effective attendance list excludes Cancelled (Missed/No-Show removed).
        $this->actingAs($admin)
            ->get(route('attendance.index', [
                'from' => '2026-10-18',
                'to' => '2026-10-18',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('records.data', [])
            );
    }

    public function test_started_visit_cannot_become_cancelled_via_reclassification(): void
    {
        ['supervisor' => $supervisor, 'scheduled' => $scheduled] = $this->completedFixture();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.reclassify-missed-cancelled', $scheduled), [
                'reason' => 'Should fail — visit already started.',
            ])
            ->assertSessionHasErrors('scheduled_visit');
    }

    public function test_material_evv_reconstruction_notifies_admin_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-18',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('attendance.corrections.store', $scheduled), [
                'is_evv_reconstruction' => true,
                'requested_clocked_in_at' => '2026-10-18T09:10',
                'requested_clocked_out_at' => '2026-10-18T16:50',
                'reason' => 'Verified service occurred; EVV missing.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction = $scheduled->fresh()->attendanceCorrections()->firstOrFail();
        $this->assertSame(AttendanceCorrectionStatus::Pending, $correction->status);

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'Verified reconstruction.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $sourceKey = 'attendance.correction.'.$correction->id.'.material.admin.'.$admin->id;
        $this->assertSame(
            1,
            InAppNotification::query()->where('source_key', $sourceKey)->count(),
        );

        // Re-notify path uses updateOrCreate — still one row.
        app(\App\Services\MaterialCorrectionAttentionService::class)
            ->notifyMaterialCorrection($correction->fresh());
        $this->assertSame(
            1,
            InAppNotification::query()->where('source_key', $sourceKey)->count(),
        );

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('operations.material_corrections', 1)
            );
    }

    public function test_payroll_hours_report_exposes_finalization_controls_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => ReportType::PayrollHours->value,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payroll_finalization.available', true)
                ->where('payroll_finalization.can.finalize', true)
                ->where('payroll_finalization.record', null)
            );
    }
}
