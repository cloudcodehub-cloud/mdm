<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\CriticalAlertCategory;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\TaskRecurrence;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientCriticalAlert;
use App\Models\ClientCriticalAlertAcknowledgment;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Models\VisitSafetyInstruction;
use App\Models\VisitTask;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase2BCriticalAlertsTest extends TestCase
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

        Carbon::setTestNow('2026-10-16 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailablePayload(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
        ];
    }

    /**
     * @return array{supervisor: Employee, dsp: Employee, client: Client, scheduled: ScheduledVisit, plan: CarePlan}
     */
    private function seededVisitContext(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
            'title' => 'Standing Plan',
            'supervision_instruction' => 'Line-of-sight during transfers',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Morning ADLs',
            'recurrence' => TaskRecurrence::Daily,
            'is_required' => true,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'supervisor_id' => $supervisor->id,
        ]);

        return compact('supervisor', 'dsp', 'client', 'scheduled', 'plan');
    }

    private function clockIn(Employee $dsp, ScheduledVisit $scheduled): Visit
    {
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        return Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
    }

    public function test_supervisor_can_create_critical_alert_within_caseload(): void
    {
        ['supervisor' => $supervisor, 'client' => $client] = $this->seededVisitContext();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('clients.critical-alerts.store', $client), [
                'category' => CriticalAlertCategory::FallRisk->value,
                'title' => 'Fall risk — gait belt',
                'body' => 'Use gait belt for all transfers.',
                'is_urgent' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_critical_alerts', [
            'client_id' => $client->id,
            'title' => 'Fall risk — gait belt',
            'is_active' => true,
            'version' => 1,
        ]);
    }

    public function test_unassigned_supervisor_cannot_manage_critical_alerts(): void
    {
        ['client' => $client] = $this->seededVisitContext();
        $other = Employee::factory()->supervisor()->create();

        $this->actingAs($other->user()->firstOrFail())
            ->post(route('clients.critical-alerts.store', $client), [
                'category' => CriticalAlertCategory::Allergy->value,
                'title' => 'Peanut allergy',
                'body' => 'No peanut products.',
            ])
            ->assertForbidden();
    }

    public function test_unacknowledged_critical_alert_does_not_block_clock_in(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();

        ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisor->user()->firstOrFail()->id,
            'title' => 'Allergy alert',
        ]);

        $visit = $this->clockIn($dsp, $scheduled);

        $this->assertSame(VisitStatus::InProgress, $visit->status);
    }

    public function test_dsp_must_acknowledge_critical_alert_before_task_execution(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $alert = ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisor->user()->firstOrFail()->id,
            'title' => 'Fall risk',
        ]);

        $visit = $this->clockIn($dsp, $scheduled);
        $task = VisitTask::query()->where('visit_id', $visit->id)->firstOrFail();
        $dspUser = $dsp->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.tasks.complete', [$visit, $task]), [])
            ->assertSessionHasErrors('critical_alerts');

        $this->actingAs($dspUser)
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertRedirect(route('visits.show', $visit));

        $this->assertDatabaseHas('client_critical_alert_acknowledgments', [
            'client_critical_alert_id' => $alert->id,
            'alert_version' => 1,
            'visit_id' => $visit->id,
            'user_id' => $dspUser->id,
        ]);

        $this->actingAs($dspUser)
            ->post(route('visits.tasks.complete', [$visit, $task]), [])
            ->assertRedirect();

        $this->assertSame(VisitTaskStatus::Completed, $task->fresh()->status);
    }

    public function test_duplicate_critical_alert_acknowledgment_is_idempotent(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $alert = ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisor->user()->firstOrFail()->id,
        ]);
        $visit = $this->clockIn($dsp, $scheduled);
        $dspUser = $dsp->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertRedirect();
        $this->actingAs($dspUser)
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertRedirect();

        $this->assertSame(1, ClientCriticalAlertAcknowledgment::query()
            ->where('client_critical_alert_id', $alert->id)
            ->where('visit_id', $visit->id)
            ->count());
    }

    public function test_material_alert_change_requires_new_acknowledgment_and_notifies_active_dsp(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $supervisorUser = $supervisor->user()->firstOrFail();
        $alert = ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisorUser->id,
            'title' => 'Original warning',
            'body' => 'Original body',
        ]);
        $visit = $this->clockIn($dsp, $scheduled);
        $dspUser = $dsp->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertRedirect();

        $this->actingAs($supervisorUser)
            ->patch(route('clients.critical-alerts.update', [$client, $alert]), [
                'category' => CriticalAlertCategory::MedicalWarning->value,
                'title' => 'Updated medical warning',
                'body' => 'New immediate guidance',
                'is_urgent' => true,
            ])
            ->assertRedirect();

        $alert->refresh();
        $this->assertSame(2, $alert->version);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $dspUser->id,
            'source_key' => sprintf('visit.%d.critical-alert.%d.v2', $visit->id, $alert->id),
            'title' => 'Urgent critical client alert',
        ]);

        $task = VisitTask::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->actingAs($dspUser)
            ->post(route('visits.tasks.complete', [$visit, $task]), [])
            ->assertSessionHasErrors('critical_alerts');
    }

    public function test_urgent_visit_safety_instruction_preserves_care_plan_snapshot(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor, 'plan' => $plan] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);
        $originalCarePlanId = $visit->care_plan_id;
        $originalInstruction = $visit->supervision_instruction;
        $originalTaskTitle = VisitTask::query()->where('visit_id', $visit->id)->value('title');
        $dspUser = $dsp->user()->firstOrFail();
        $supervisorUser = $supervisor->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->post(route('visits.safety-instructions.store', $visit), [
                'content' => 'Stay within arm reach for the remainder of this visit.',
                'is_urgent' => true,
            ])
            ->assertRedirect(route('visits.show', $visit));

        $visit->refresh();
        $this->assertSame($originalCarePlanId, $visit->care_plan_id);
        $this->assertSame($plan->id, $visit->care_plan_id);
        $this->assertSame($originalInstruction, $visit->supervision_instruction);
        $this->assertSame($originalTaskTitle, VisitTask::query()->where('visit_id', $visit->id)->value('title'));

        $instruction = VisitSafetyInstruction::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertNotNull(InAppNotification::query()
            ->where('user_id', $dspUser->id)
            ->where('source_key', sprintf('visit.%d.safety-instruction.%d', $visit->id, $instruction->id))
            ->first());

        $task = VisitTask::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->actingAs($dspUser)
            ->post(route('visits.tasks.complete', [$visit, $task]), [])
            ->assertSessionHasErrors('visit_safety_instructions');

        $this->actingAs($dspUser)
            ->post(route('visits.safety-instructions.acknowledge', [$visit, $instruction]))
            ->assertRedirect();

        $this->assertNotNull($instruction->fresh()->acknowledged_at);

        $this->actingAs($dspUser)
            ->post(route('visits.tasks.complete', [$visit, $task]), [])
            ->assertRedirect();
    }

    public function test_dsp_cannot_issue_visit_safety_instruction(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.safety-instructions.store', $visit), [
                'content' => 'Unauthorized instruction',
            ])
            ->assertForbidden();
    }

    public function test_cannot_acknowledge_on_completed_visit(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $alert = ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisor->user()->firstOrFail()->id,
        ]);
        $visit = $this->clockIn($dsp, $scheduled);
        $visit->forceFill([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now(),
            'handover_note' => 'Done',
        ])->save();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertForbidden();
    }

    public function test_unauthorized_dsp_cannot_acknowledge_another_dsps_visit_alert(): void
    {
        ['dsp' => $dsp, 'client' => $client, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $alert = ClientCriticalAlert::factory()->forClient($client)->create([
            'created_by_user_id' => $supervisor->user()->firstOrFail()->id,
        ]);
        $visit = $this->clockIn($dsp, $scheduled);

        $this->actingAs($otherDsp->user()->firstOrFail())
            ->post(route('visits.critical-alerts.acknowledge', [$visit, $alert]))
            ->assertForbidden();
    }
}
