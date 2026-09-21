<?php

namespace Tests\Feature\Documents;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\CarePlanStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\AttendanceCorrection;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\SkipReason;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MdmDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-21 14:30:00', 'UTC'));
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
     *     outsider: Employee,
     *     client: Client,
     *     scheduled: ScheduledVisit,
     *     includedTitle: string,
     *     excludedTitle: string,
     *     oneOffTitle: string,
     *     authorizationNumber: string
     * }
     */
    private function handoutWorld(): array
    {
        $admin = User::factory()->admin()->create(['name' => 'Avery Admin']);
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $outsider = Employee::factory()->dsp()->forSupervisor($other)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create([
            'client_number' => 'CLT-3001',
        ]);
        $authorization = ClientAuthorization::factory()->forClient($client)->create([
            'authorization_number' => 'AUTH-LEAK-999',
        ]);
        $plan = CarePlan::factory()->forClient($client)->create([
            'status' => CarePlanStatus::Active,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $included = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Morning ADLs',
            'instructions' => 'Support hygiene and breakfast.',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $excluded = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Community outing',
            'instructions' => 'Support a community activity.',
            'sort_order' => 2,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-21',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'supervisor_id' => $supervisor->id,
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'Omit community outing. Pharmacy pickup is visit-only.',
        ]);
        ScheduledVisitTaskOverride::query()->create([
            'scheduled_visit_id' => $scheduled->id,
            'care_plan_task_template_id' => $excluded->id,
            'included' => false,
            'exclusion_reason' => 'Not planned for this visit.',
        ]);
        ScheduledVisitOneOffTask::query()->create([
            'scheduled_visit_id' => $scheduled->id,
            'title' => 'Pharmacy pickup support',
            'instructions' => 'Escort to the pharmacy and return with the refill.',
            'is_required' => true,
            'note_required' => true,
            'sort_order' => 10,
        ]);

        return [
            'admin' => $admin,
            'supervisor' => $supervisor,
            'other' => $other,
            'dsp' => $dsp,
            'outsider' => $outsider,
            'client' => $client,
            'scheduled' => $scheduled,
            'includedTitle' => $included->title,
            'excludedTitle' => $excluded->title,
            'oneOffTitle' => 'Pharmacy pickup support',
            'authorizationNumber' => $authorization->authorization_number,
        ];
    }

    private function configureAgency(): void
    {
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Light Supports',
            'timezone' => 'America/Chicago',
            'date_format' => DateFormat::Iso,
            'time_format' => TimeFormat::TwentyFourHour,
        ]);
    }

    public function test_framework_uses_agency_timezone_and_generated_by_identity(): void
    {
        $this->configureAgency();
        ['admin' => $admin, 'scheduled' => $scheduled] = $this->handoutWorld();

        $this->actingAs($admin)
            ->get(route('documents.visit-handout.preview', $scheduled))
            ->assertOk()
            ->assertSee('Harbor Light Supports', false)
            ->assertSeeText('Client Visit & Task Handout')
            ->assertSee('Generated by: Avery Admin · Administrator', false)
            ->assertSee('America/Chicago', false)
            ->assertSee('2026-09-21 09:30', false)
            ->assertSee('Confidential — Contains agency operational and care information', false)
            ->assertDontSee('Ultimate Care Supported Living', false);

        $pdf = $this->actingAs($admin)
            ->get(route('documents.visit-handout.pdf', $scheduled));

        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', strtolower((string) $pdf->headers->get('content-disposition')));
        $this->assertStringContainsString('Harbor-Light_Visit-Handout_CLT-3001_2026-09-21.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertGreaterThan(1000, strlen((string) $pdf->getContent()));
    }

    public function test_unauthorized_direct_document_routes_are_forbidden(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();
        $scheduled = $world['scheduled'];
        $otherUser = $world['other']->user()->firstOrFail();
        $dspUser = $world['dsp']->user()->firstOrFail();
        $outsiderUser = $world['outsider']->user()->firstOrFail();

        $this->actingAs($otherUser)
            ->get(route('documents.visit-handout.preview', $scheduled))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->get(route('documents.visit-handout.pdf', $scheduled))
            ->assertForbidden();

        $this->actingAs($outsiderUser)
            ->get(route('documents.visit-handout.pdf', $scheduled))
            ->assertForbidden();

        $this->actingAs($dspUser)
            ->get(route('documents.hours-attendance.preview', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertForbidden();
        $this->actingAs($dspUser)
            ->get(route('documents.hours-attendance.pdf', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertForbidden();
    }

    public function test_dsp_can_open_assigned_handout_and_cannot_open_other_dsp_visit(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();
        $dspUser = $world['dsp']->user()->firstOrFail();
        $outsiderUser = $world['outsider']->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->get(route('documents.visit-handout.preview', $world['scheduled']))
            ->assertOk()
            ->assertSee('Morning ADLs', false);

        $this->actingAs($outsiderUser)
            ->get(route('documents.visit-handout.preview', $world['scheduled']))
            ->assertForbidden();
    }

    public function test_visit_handout_prints_this_visits_task_set_only(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();

        $this->actingAs($world['admin'])
            ->get(route('documents.visit-handout.preview', $world['scheduled']))
            ->assertOk()
            ->assertSee('Morning ADLs', false)
            ->assertSee('Pharmacy pickup support', false)
            ->assertSee('Visit-only', false)
            ->assertDontSee('Community outing', false)
            ->assertDontSee('AUTH-LEAK-999', false)
            ->assertDontSee('Ohio Medicaid', false);
    }

    public function test_completed_visit_report_preserves_task_outcomes_and_clock_history(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();
        $scheduled = $world['scheduled'];
        $scheduled->update(['status' => ScheduledVisitStatus::Completed]);
        $originalIn = Carbon::parse('2026-09-20 12:08:00', 'UTC');
        $originalOut = Carbon::parse('2026-09-20 19:52:00', 'UTC');
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => $originalIn,
            'clocked_out_at' => $originalOut,
            'visit_notes' => 'Morning ADLs, meals, and safety check completed.',
            'handover_note' => 'Refill picked up. Community outing omitted.',
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Unavailable,
        ]);
        $reason = SkipReason::factory()->create(['name' => 'Client declined']);
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'title' => 'Morning ADLs',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Completed as accepted.',
            'care_plan_task_template_id' => null,
        ]);
        $oneOff = $scheduled->oneOffTasks()->firstOrFail();
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'title' => 'Pharmacy pickup support',
            'status' => VisitTaskStatus::Skipped,
            'skip_reason_id' => $reason->id,
            'skip_comment' => 'Pharmacy closed at arrival.',
            'scheduled_visit_one_off_task_id' => $oneOff->id,
            'care_plan_task_template_id' => null,
        ]);
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'title' => 'Safety check',
            'status' => VisitTaskStatus::Pending,
            'care_plan_task_template_id' => null,
        ]);
        AttendanceCorrection::factory()->forVisit($visit)->create([
            'status' => AttendanceCorrectionStatus::Approved,
            'original_clocked_in_at' => $originalIn,
            'original_clocked_out_at' => $originalOut,
            'requested_clocked_in_at' => $originalIn->addMinutes(7),
            'requested_clocked_out_at' => $originalOut->addMinutes(5),
            'requested_by_user_id' => $world['supervisor']->user_id,
            'reviewed_by_user_id' => $world['admin']->id,
            'reviewed_at' => now(),
            'review_note' => 'Approved after timesheet review.',
        ]);

        $preview = $this->actingAs($world['admin'])
            ->get(route('documents.completed-visit.preview', $visit))
            ->assertOk();

        $preview->assertSeeText('Completed Visit Report')
            ->assertSee('Morning ADLs', false)
            ->assertSee('Completed as accepted.', false)
            ->assertSee('Pharmacy pickup support', false)
            ->assertSee('Client declined', false)
            ->assertSee('Pharmacy closed at arrival.', false)
            ->assertSee('Safety check', false)
            ->assertSee('Original recorded', false)
            ->assertSee('Corrected / effective', false)
            ->assertSee('Morning ADLs, meals, and safety check completed.', false)
            ->assertSee('Refill picked up. Community outing omitted.', false)
            ->assertDontSee('AUTH-LEAK-999', false);

        $this->actingAs($world['other']->user()->firstOrFail())
            ->get(route('documents.completed-visit.pdf', $visit))
            ->assertForbidden();

        $this->actingAs($world['dsp']->user()->firstOrFail())
            ->get(route('documents.completed-visit.preview', $visit))
            ->assertOk();

        $otherScheduled = ScheduledVisit::factory()->forClient($world['client'])->forDsp($world['dsp'])->create([
            'service_date' => '2026-09-22',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'supervisor_id' => $world['supervisor']->id,
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $inProgress = Visit::factory()->forScheduledVisit($otherScheduled)->create([
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => $originalIn,
        ]);
        $this->actingAs($world['admin'])
            ->get(route('documents.completed-visit.preview', $inProgress))
            ->assertNotFound();

        $pdf = $this->actingAs($world['admin'])
            ->get(route('documents.completed-visit.pdf', $visit));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertGreaterThan(1000, strlen((string) $pdf->getContent()));
    }

    public function test_hours_report_respects_scope_filters_and_omits_payroll_fields(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();
        $scheduled = $world['scheduled'];
        $scheduled->update([
            'status' => ScheduledVisitStatus::Completed,
            'service_date' => '2026-09-20',
        ]);
        Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-20 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-20 20:00:00', 'UTC'),
        ]);

        $otherClient = Client::factory()->forSupervisor($world['other'])->create();
        $otherDsp = $world['outsider'];
        $otherVisit = ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create([
            'service_date' => '2026-09-20',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'supervisor_id' => $world['other']->id,
            'status' => ScheduledVisitStatus::Completed,
        ]);
        Visit::factory()->forScheduledVisit($otherVisit)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-20 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-20 20:00:00', 'UTC'),
        ]);

        $filters = [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'employee_id' => (string) $world['dsp']->id,
        ];

        $adminPreview = $this->actingAs($world['admin'])
            ->get(route('documents.hours-attendance.preview', $filters))
            ->assertOk();

        $adminPreview->assertSeeText('Employee Hours & Attendance Report')
            ->assertSee($world['dsp']->full_name, false)
            ->assertSee($world['dsp']->employee_number, false)
            ->assertSee($world['client']->full_name, false)
            ->assertSee('8.00', false)
            ->assertDontSee('hourly rate', false)
            ->assertDontSee('Gross pay', false)
            ->assertDontSee('Net pay', false)
            ->assertDontSee('tax withholding', false);

        $this->actingAs($world['supervisor']->user()->firstOrFail())
            ->get(route('documents.hours-attendance.preview', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee($world['dsp']->full_name, false)
            ->assertDontSee($otherDsp->full_name, false);

        $this->actingAs($world['admin'])
            ->get(route('documents.hours-attendance.preview', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee($world['dsp']->full_name, false)
            ->assertSee($otherDsp->full_name, false);

        $pdf = $this->actingAs($world['admin'])
            ->get(route('documents.hours-attendance.pdf', $filters));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertGreaterThan(1000, strlen((string) $pdf->getContent()));
        $this->assertStringContainsString('Hours', (string) $pdf->headers->get('content-disposition'));
    }

    public function test_generated_pdfs_are_non_empty_multipage_safe_binaries(): void
    {
        $this->configureAgency();
        $world = $this->handoutWorld();
        $scheduled = $world['scheduled'];
        $scheduled->update(['status' => ScheduledVisitStatus::Completed, 'service_date' => '2026-09-20']);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-09-20 12:00:00', 'UTC'),
            'clocked_out_at' => Carbon::parse('2026-09-20 20:00:00', 'UTC'),
        ]);
        foreach (range(1, 24) as $index) {
            VisitTask::factory()->create([
                'visit_id' => $visit->id,
                'title' => 'Task row '.$index.' for multipage layout',
                'instructions' => 'Longer instruction text so the completed visit report paginates safely.',
                'status' => VisitTaskStatus::Completed,
                'care_plan_task_template_id' => null,
            ]);
        }

        $dir = storage_path('app/testing/pdf-validation');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $handout = $this->actingAs($world['admin'])
            ->get(route('documents.visit-handout.pdf', $scheduled));
        $completed = $this->actingAs($world['admin'])
            ->get(route('documents.completed-visit.pdf', $visit));
        $hours = $this->actingAs($world['admin'])
            ->get(route('documents.hours-attendance.pdf', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]));

        foreach ([
            'visit-handout.pdf' => $handout,
            'completed-visit.pdf' => $completed,
            'hours-attendance.pdf' => $hours,
        ] as $name => $response) {
            $response->assertOk();
            $binary = (string) $response->getContent();
            $this->assertStringStartsWith('%PDF', $binary);
            $this->assertGreaterThan(1500, strlen($binary));
            file_put_contents($dir.'/'.$name, $binary);
        }
    }
}
