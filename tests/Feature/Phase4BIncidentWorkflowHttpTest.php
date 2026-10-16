<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\IncidentStatus;
use App\Enums\TimeFormat;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentFollowUp;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase4BIncidentWorkflowHttpTest extends TestCase
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
     * @return array{supervisor: Employee, dsp: Employee, client: Client, scheduled: ScheduledVisit}
     */
    private function context(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01', 'ends_on' => null]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'supervisor_id' => $supervisor->id,
        ]);

        return compact('supervisor', 'dsp', 'client', 'scheduled');
    }

    private function clockIn(Employee $dsp, ScheduledVisit $scheduled): Visit
    {
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
                'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
            ])
            ->assertRedirect();

        return Visit::query()->where('scheduled_visit_id', $scheduled->id)->firstOrFail();
    }

    private function underReviewIncident(): array
    {
        $ctx = $this->context();
        $visit = $this->clockIn($ctx['dsp'], $ctx['scheduled']);
        $incident = Incident::factory()->forVisit($visit)->underReview()->create();

        return [$ctx, $incident];
    }

    private function adminUser(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_manager_completes_workflow_and_closes_then_admin_reopens(): void
    {
        [$ctx, $incident] = $this->underReviewIncident();
        $supervisorUser = $ctx['supervisor']->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->get(route('incidents.show', $incident))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.manage', true)
                ->where('can.close', true)
                ->where('can.reopen', false)
                ->has('categories')
                ->has('assignee_options'));

        $this->actingAs($supervisorUser)
            ->patch(route('incidents.classification.update', $incident), [
                'official_category' => 'Injury / medical',
                'official_subcategory' => 'Fall',
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->patch(route('incidents.classification.update', $incident), [
                'official_category' => 'Injury / medical',
                'official_subcategory' => 'Not a real sub',
            ])
            ->assertSessionHasErrors('official_subcategory');

        $this->actingAs($supervisorUser)
            ->post(route('incidents.reviews.store', $incident), ['note' => 'Reviewed with DSP.'])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->patch(route('incidents.investigation.update', $incident), [
                'finding' => 'Wet floor.',
                'cause_contributing_factors' => 'Spill not cleaned.',
                'prevention_plan' => 'Add floor checks.',
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->post(route('incidents.follow-ups.store', $incident), [
                'action_type' => 'Retrain DSP',
                'assigned_employee_id' => $ctx['dsp']->id,
                'due_on' => '2026-10-20',
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $followUp = IncidentFollowUp::query()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->post(route('incidents.close', $incident), ['closure_note' => 'Done.'])
            ->assertSessionHasErrors('closure');

        $this->actingAs($supervisorUser)
            ->patch(route('incidents.follow-ups.reassign', $followUp), [
                'assigned_employee_id' => $ctx['supervisor']->id,
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->post(route('incidents.follow-ups.complete', $followUp), ['completion_note' => 'Retrained.'])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->post(route('incidents.reporting-records.store', $incident), [
                'reported_to' => 'State licensing',
                'method' => 'Phone',
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $this->actingAs($supervisorUser)
            ->post(route('incidents.close', $incident), ['closure_note' => 'All actions complete.'])
            ->assertRedirect(route('incidents.show', $incident));

        $this->assertSame(IncidentStatus::Closed, $incident->refresh()->status);

        // Final document: manage only, closed only.
        $this->actingAs($supervisorUser)
            ->get(route('documents.incident-final.preview', $incident))
            ->assertOk();
        $this->actingAs($supervisorUser)
            ->get(route('documents.incident-final.pdf', $incident))
            ->assertOk();
        $this->actingAs($supervisorUser)
            ->get(route('documents.incident-submitted.preview', $incident))
            ->assertOk();

        // Supervisor cannot reopen.
        $this->actingAs($supervisorUser)
            ->post(route('incidents.reopen', $incident), ['reopen_reason' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($this->adminUser())
            ->post(route('incidents.reopen', $incident), ['reopen_reason' => 'New evidence.'])
            ->assertRedirect(route('incidents.show', $incident));

        $this->assertSame(IncidentStatus::UnderReview, $incident->refresh()->status);
    }

    public function test_final_document_is_not_available_before_closure_and_not_for_reporter(): void
    {
        [$ctx, $incident] = $this->underReviewIncident();
        $supervisorUser = $ctx['supervisor']->user()->firstOrFail();
        $dspUser = $ctx['dsp']->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->get(route('documents.incident-final.preview', $incident))
            ->assertNotFound();

        $this->actingAs($dspUser)
            ->get(route('documents.incident-submitted.pdf', $incident))
            ->assertOk();

        $this->actingAs($dspUser)
            ->get(route('documents.incident-final.preview', $incident))
            ->assertForbidden();
    }

    public function test_reporter_dsp_can_amend_but_cannot_use_management_endpoints(): void
    {
        [$ctx, $incident] = $this->underReviewIncident();
        $dspUser = $ctx['dsp']->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('incidents.amendments.store', $incident), [
                'field_key' => 'summary',
                'new_content' => 'Corrected summary.',
                'reason' => 'Typo',
            ])
            ->assertRedirect(route('incidents.show', $incident));

        $this->assertSame('Corrected summary.', $incident->refresh()->summary);

        $this->actingAs($dspUser)
            ->post(route('incidents.amendments.store', $incident), [
                'field_key' => 'finding',
                'new_content' => 'x',
                'reason' => 'x',
            ])
            ->assertSessionHasErrors('field_key');

        $this->actingAs($dspUser)
            ->post(route('incidents.reviews.store', $incident), ['note' => 'Hi'])
            ->assertForbidden();

        $this->actingAs($dspUser)
            ->post(route('incidents.close', $incident), ['closure_note' => 'Hi'])
            ->assertForbidden();
    }

    public function test_incident_submit_requires_injury_type_and_time_when_injured(): void
    {
        $ctx = $this->context();
        $visit = $this->clockIn($ctx['dsp'], $ctx['scheduled']);
        $dspUser = $ctx['dsp']->user()->firstOrFail();

        $payload = [
            'occurred_at' => '2026-10-16T11:45',
            'original_category' => 'Injury / medical',
            'original_subcategory' => 'Fall',
            'summary' => 'Fall.',
            'immediate_action' => 'First aid.',
            'injured' => true,
            'intervention' => false,
            'supervisor_notified' => true,
            'submit' => true,
        ];

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), $payload)
            ->assertSessionHasErrors(['injury_type', 'injury_occurred_at']);

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), [
                ...$payload,
                'injury_type' => 'Bruise',
                'injury_occurred_at' => '2026-10-16T11:45',
                'parties' => [
                    ['party_kind' => 'primary_client'],
                ],
            ])
            ->assertSessionHasErrors('parties.0.party_kind');

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), [
                ...$payload,
                'injury_type' => 'Bruise',
                'injury_occurred_at' => '2026-10-16T11:45',
                'parties' => [
                    ['party_kind' => 'outside', 'name' => 'Neighbor', 'involvement' => 'Witness'],
                ],
            ])
            ->assertSessionDoesntHaveErrors();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame(IncidentStatus::Submitted, $incident->status);
        $this->assertSame(2, $incident->parties()->count());
    }

    public function test_clock_out_can_submit_incident_draft_and_rejects_submit_plus_discard(): void
    {
        $ctx = $this->context();
        $visit = $this->clockIn($ctx['dsp'], $ctx['scheduled']);
        $dspUser = $ctx['dsp']->user()->firstOrFail();

        $this->actingAs($dspUser)
            ->post(route('visits.incidents.store', $visit), [
                'occurred_at' => '2026-10-16T11:45',
                'original_category' => 'Injury / medical',
                'original_subcategory' => 'Fall',
                'summary' => 'Fall.',
                'immediate_action' => 'First aid.',
                'injured' => false,
                'intervention' => false,
                'supervisor_notified' => true,
                'submit' => false,
                'parties' => [
                    ['party_kind' => 'outside', 'name' => 'Witness A', 'involvement' => 'Saw fall'],
                ],
            ])
            ->assertRedirect();

        $draft = Incident::query()->firstOrFail();
        $this->assertSame(2, $draft->parties()->count());

        $base = [
            'handover_note' => 'All good.',
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
        ];

        $this->actingAs($dspUser)
            ->post(route('visits.clock-out', $visit), [
                ...$base,
                'submit_incident_draft' => true,
                'discard_incident_draft' => true,
            ])
            ->assertSessionHasErrors('incident_draft');

        $this->actingAs($dspUser)
            ->post(route('visits.clock-out', $visit), [
                ...$base,
                'acknowledge_unfinished_required' => true,
                'submit_incident_draft' => true,
            ])
            ->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame(IncidentStatus::Submitted, $incident->status);
        $this->assertSame(2, $incident->parties()->count());
        $this->assertTrue($incident->parties()->where('name', 'Witness A')->exists());
    }
}
