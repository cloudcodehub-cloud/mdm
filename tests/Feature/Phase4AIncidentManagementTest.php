<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\IncidentHistoryAction;
use App\Enums\IncidentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\TimeFormat;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\Incident;
use App\Models\OrganizationSetting;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\IncidentService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase4AIncidentManagementTest extends TestCase
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
            'incident_acknowledgment_minutes' => 60,
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
     * @return array{supervisor: Employee, otherSupervisor: Employee, dsp: Employee, otherDsp: Employee, client: Client, scheduled: ScheduledVisit}
     */
    private function seededVisitContext(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-10-01',
            'ends_on' => null,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'supervisor_id' => $supervisor->id,
        ]);

        return compact('supervisor', 'otherSupervisor', 'dsp', 'otherDsp', 'client', 'scheduled');
    }

    private function clockIn(Employee $dsp, ScheduledVisit $scheduled): Visit
    {
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->unavailablePayload())
            ->assertRedirect();

        return Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'occurred_at' => '2026-10-16T11:45',
            'original_category' => 'Injury / medical',
            'original_subcategory' => 'Fall',
            'summary' => 'Client slipped near doorway during transfer.',
            'immediate_action' => 'Assisted to chair and checked for injury.',
            'injured' => false,
            'intervention' => false,
            'supervisor_notified' => true,
            'additional_notes' => 'No visible injury.',
            'submit' => false,
        ], $overrides);
    }

    public function test_dsp_can_save_draft_and_submit_incident_from_active_visit(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'scheduled' => $scheduled] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);
        $dspUser = $dsp->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), $this->reportPayload())
            ->assertRedirect(route('visits.show', $visit));

        $draft = Incident::query()->firstOrFail();
        $this->assertSame(IncidentStatus::Draft, $draft->status);
        $this->assertNull($draft->tracking_number);
        $this->assertDatabaseMissing('incident_history_events', [
            'action' => IncidentHistoryAction::Submitted->value,
        ]);

        $this->actingAs($dspUser)
            ->patch(route('incidents.update', $draft), $this->reportPayload([
                'submit' => true,
                'summary' => 'Updated fall narrative for submission.',
            ]))
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(IncidentStatus::Submitted, $draft->status);
        $this->assertNotNull($draft->tracking_number);
        $this->assertNotNull($draft->submitted_at);
        $this->assertSame($supervisor->id, $draft->responsible_supervisor_id);
        $this->assertSame('Updated fall narrative for submission.', $draft->summary);

        $this->assertDatabaseHas('incident_history_events', [
            'incident_id' => $draft->id,
            'action' => IncidentHistoryAction::Submitted->value,
        ]);

        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $supervisor->user()->firstOrFail()->id)
                ->where('type', InAppNotificationType::Incident)
                ->exists(),
        );
    }

    public function test_draft_is_private_and_unassigned_dsp_cannot_submit(): void
    {
        ['dsp' => $dsp, 'otherDsp' => $otherDsp, 'scheduled' => $scheduled, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.incidents.store', $visit), $this->reportPayload())
            ->assertRedirect();

        $draft = Incident::query()->firstOrFail();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('incidents.show', $draft))
            ->assertForbidden();

        $this->actingAs($otherDsp->user()->firstOrFail())
            ->post(route('visits.incidents.store', $visit), $this->reportPayload(['submit' => true]))
            ->assertForbidden();
    }

    public function test_supervisor_caseload_and_admin_can_acknowledge_and_out_of_caseload_cannot(): void
    {
        [
            'supervisor' => $supervisor,
            'otherSupervisor' => $otherSupervisor,
            'dsp' => $dsp,
            'scheduled' => $scheduled,
        ] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);
        $admin = User::factory()->admin()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.incidents.store', $visit), $this->reportPayload(['submit' => true]))
            ->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $acknowledgedAt = $incident->submitted_at?->copy();

        $this->actingAs($otherSupervisor->user()->firstOrFail())
            ->get(route('incidents.show', $incident))
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('incidents.acknowledge', $incident))
            ->assertRedirect(route('incidents.show', $incident));

        $incident->refresh();
        $this->assertSame(IncidentStatus::UnderReview, $incident->status);
        $this->assertNotNull($incident->acknowledged_at);
        $this->assertSame($supervisor->user()->firstOrFail()->id, $incident->acknowledged_by_user_id);
        $this->assertNotNull($acknowledgedAt);

        $this->assertDatabaseHas('incident_history_events', [
            'incident_id' => $incident->id,
            'action' => IncidentHistoryAction::Acknowledged->value,
        ]);

        // Admin may also acknowledge a different submitted incident.
        $second = Incident::factory()->forVisit($visit)->submitted()->create([
            'responsible_supervisor_id' => $supervisor->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('incidents.acknowledge', $second))
            ->assertRedirect();

        $second->refresh();
        $this->assertSame(IncidentStatus::UnderReview, $second->status);
    }

    public function test_acknowledgment_overdue_escalation_and_threshold_change_preserves_history(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'scheduled' => $scheduled] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);
        $admin = User::factory()->admin()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.incidents.store', $visit), $this->reportPayload(['submit' => true]))
            ->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $incident->update([
            'submitted_at' => now()->subMinutes(61),
        ]);
        $originalSubmittedAt = $incident->fresh()->submitted_at?->toIso8601String();

        $count = app(IncidentService::class)->escalateOverdueAcknowledgments();
        $this->assertSame(1, $count);

        $incident->refresh();
        $this->assertNotNull($incident->acknowledgment_overdue_notified_at);
        $this->assertSame(IncidentStatus::Submitted, $incident->status);
        $this->assertTrue(app(IncidentService::class)->isOverdueForAcknowledgment($incident));

        $this->assertDatabaseHas('incident_history_events', [
            'incident_id' => $incident->id,
            'action' => IncidentHistoryAction::AcknowledgmentOverdue->value,
        ]);

        $this->actingAs($admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('operations.incidents.overdue_for_acknowledgment', 1));

        $this->actingAs($admin)
            ->patch(route('settings.general.update'), [
                'organization_name' => 'MDM - Magic Data Management',
                'timezone' => 'UTC',
                'date_format' => DateFormat::MonthDayYear->value,
                'time_format' => TimeFormat::TwelveHour->value,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => 90,
                'staffing_needed_urgency_hours' => 24,
            ])
            ->assertRedirect();

        $this->assertSame(90, (int) OrganizationSetting::query()->value('incident_acknowledgment_minutes'));
        $this->assertSame(
            $originalSubmittedAt,
            $incident->fresh()->submitted_at?->toIso8601String(),
        );
        $this->assertNull($incident->fresh()->acknowledged_at);

        $this->assertDatabaseHas('incident_history_events', [
            'action' => IncidentHistoryAction::ThresholdConfigured->value,
            'old_value' => '60',
            'new_value' => '90',
        ]);
    }

    public function test_clock_out_blocked_by_draft_until_discarded(): void
    {
        ['dsp' => $dsp, 'scheduled' => $scheduled] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);
        $dspUser = $dsp->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), $this->reportPayload())
            ->assertRedirect();

        $this->actingAs($dspUser)
            ->post(route('visits.clock-out', $visit), [
                ...$this->unavailablePayload(),
                'handover_note' => 'Wrap-up note',
            ])
            ->assertSessionHasErrors('incident_draft');

        $this->actingAs($dspUser)
            ->post(route('visits.clock-out', $visit), [
                ...$this->unavailablePayload(),
                'handover_note' => 'Wrap-up note',
                'discard_incident_draft' => true,
            ])
            ->assertRedirect(route('visits.show', $visit));

        $this->assertNotNull($visit->fresh()->clocked_out_at);
        $this->assertNotNull(Incident::query()->firstOrFail()->discarded_at);
    }

    public function test_dsp_register_shows_own_submitted_incidents_only(): void
    {
        ['dsp' => $dsp, 'otherDsp' => $otherDsp, 'scheduled' => $scheduled, 'client' => $client, 'supervisor' => $supervisor] = $this->seededVisitContext();
        $visit = $this->clockIn($dsp, $scheduled);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.incidents.store', $visit), $this->reportPayload(['submit' => true]))
            ->assertRedirect();

        $own = Incident::query()->firstOrFail();

        $otherClient = Client::factory()->forSupervisor($supervisor)->create();
        $otherVisit = Visit::factory()->create([
            'client_id' => $otherClient->id,
            'employee_id' => $otherDsp->id,
        ]);
        Incident::factory()->forVisit($otherVisit)->submitted()->create([
            'reporter_employee_id' => $otherDsp->id,
            'responsible_supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('incidents.data', 1)
                ->where('incidents.data.0.id', $own->id));
    }
}
