<?php

namespace Tests\Feature\Documents;

use App\Enums\DateFormat;
use App\Enums\IncidentStatus;
use App\Enums\RetainedDocumentKey;
use App\Enums\TimeFormat;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentFollowUp;
use App\Models\IncidentReview;
use App\Models\RetainedDocumentArtifact;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Documents\RetainedDocumentService;
use App\Services\IncidentService;
use App\Services\IncidentWorkflowService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9CRetainedPdfSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Storage::fake('local');

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Retained Docs',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
            'incident_acknowledgment_minutes' => 60,
        ]);

        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{supervisor: Employee, dsp: Employee, client: Client, visit: Visit, admin: User}
     */
    private function world(): array
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01', 'ends_on' => null]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-21',
            'supervisor_id' => $supervisor->id,
        ]);
        $visit = Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
        ]);

        return compact('supervisor', 'dsp', 'client', 'visit', 'admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submitPayload(array $overrides = []): array
    {
        return array_merge([
            'occurred_at' => '2026-10-21T11:00',
            'original_category' => 'Injury / medical',
            'original_subcategory' => 'Fall',
            'summary' => 'Client slipped near doorway during transfer.',
            'immediate_action' => 'Assisted to chair and checked for injury.',
            'injured' => false,
            'intervention' => false,
            'supervisor_notified' => true,
            'additional_notes' => 'No visible injury.',
        ], $overrides);
    }

    private function submitIncident(Employee $dsp, Visit $visit): Incident
    {
        return app(IncidentService::class)->submit(
            $dsp->user()->firstOrFail(),
            $visit,
            $this->submitPayload(),
        );
    }

    private function prepareCloseable(Incident $incident, User $manager): void
    {
        $workflow = app(IncidentWorkflowService::class);

        if ($incident->status === IncidentStatus::Submitted) {
            $incident->update([
                'status' => IncidentStatus::UnderReview,
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => $manager->id,
            ]);
        }

        $workflow->updateOfficialClassification($manager, $incident->refresh(), [
            'official_category' => 'Injury / medical',
            'official_subcategory' => 'Fall',
        ]);

        IncidentReview::query()->create([
            'incident_id' => $incident->id,
            'reviewer_user_id' => $manager->id,
            'note' => 'Reviewed with DSP.',
        ]);

        $incident->update([
            'finding' => 'Wet floor near entrance.',
            'cause_contributing_factors' => 'Spill not cleaned promptly.',
            'prevention_plan' => 'Add hourly floor checks.',
        ]);
    }

    private function closeIncident(User $manager, Incident $incident, string $note = 'All actions complete.'): Incident
    {
        return app(IncidentWorkflowService::class)->close($manager, $incident->refresh(), [
            'closure_note' => $note,
        ]);
    }

    public function test_submit_retains_immutable_submitted_pdf_snapshot(): void
    {
        ['dsp' => $dsp, 'visit' => $visit] = $this->world();

        $incident = $this->submitIncident($dsp, $visit);

        $artifact = RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentSubmitted)
            ->first();

        $this->assertNotNull($artifact);
        $this->assertSame(1, $artifact->version);
        $this->assertTrue($artifact->is_current);
        $this->assertTrue(Storage::disk('local')->exists($artifact->path));
        $checksum = $artifact->checksum_sha256;
        $bytes = Storage::disk('local')->get($artifact->path);

        $again = app(RetainedDocumentService::class)->retainIncidentSubmitted(
            $incident->refresh(),
            $dsp->user()->firstOrFail(),
        );

        $this->assertSame($artifact->id, $again->id);
        $this->assertSame(1, RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentSubmitted)
            ->count());
        $this->assertSame($checksum, $again->fresh()->checksum_sha256);
        $this->assertSame($bytes, Storage::disk('local')->get($artifact->path));

        $response = $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('documents.incident-submitted.pdf', $incident));

        $response->assertOk();
        $this->assertSame($bytes, $response->streamedContent());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_close_reopen_reclose_creates_new_final_version_without_overwriting_prior(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'visit' => $visit, 'admin' => $admin] = $this->world();
        $manager = $supervisor->user()->firstOrFail();

        $incident = $this->submitIncident($dsp, $visit);
        $this->prepareCloseable($incident, $manager);
        $this->closeIncident($manager, $incident, 'First closure.');

        $v1 = RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentFinal)
            ->where('version', 1)
            ->firstOrFail();

        $this->assertTrue($v1->is_current);
        $v1Checksum = $v1->checksum_sha256;
        $v1Bytes = Storage::disk('local')->get($v1->path);

        app(IncidentWorkflowService::class)->reopen($admin, $incident->refresh(), [
            'reopen_reason' => 'New evidence.',
        ]);

        $this->assertSame(IncidentStatus::UnderReview, $incident->refresh()->status);
        $this->assertTrue($v1->fresh()->is_current);

        $incident->update([
            'finding' => 'Updated finding after reopen.',
            'prevention_plan' => 'Revised prevention plan.',
        ]);

        IncidentReview::query()->create([
            'incident_id' => $incident->id,
            'reviewer_user_id' => $manager->id,
            'note' => 'Second review after reopen.',
        ]);

        $this->closeIncident($manager, $incident->refresh(), 'Second closure after reopen.');

        $v1->refresh();
        $v2 = RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentFinal)
            ->where('version', 2)
            ->firstOrFail();

        $this->assertFalse($v1->is_current);
        $this->assertNotNull($v1->superseded_at);
        $this->assertSame($v2->id, $v1->superseded_by_artifact_id);
        $this->assertTrue($v2->is_current);
        $this->assertSame($v1Checksum, $v1->checksum_sha256);
        $this->assertSame($v1Bytes, Storage::disk('local')->get($v1->path));
        $this->assertNotSame($v1Checksum, $v2->checksum_sha256);

        $currentDownload = $this->actingAs($manager)
            ->get(route('documents.incident-final.pdf', $incident));
        $currentDownload->assertOk();
        $this->assertSame(Storage::disk('local')->get($v2->path), $currentDownload->streamedContent());

        $historical = $this->actingAs($manager)
            ->get(route('documents.retained.download', $v1));
        $historical->assertOk();
        $this->assertSame($v1Bytes, $historical->streamedContent());
    }

    public function test_authorization_enforced_for_retained_final_and_submitted(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'visit' => $visit] = $this->world();
        $outsider = Employee::factory()->dsp()->create();
        $manager = $supervisor->user()->firstOrFail();

        $incident = $this->submitIncident($dsp, $visit);
        $this->prepareCloseable($incident, $manager);
        $this->closeIncident($manager, $incident);

        $submitted = RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentSubmitted)
            ->firstOrFail();
        $final = RetainedDocumentArtifact::query()
            ->where('source_id', $incident->id)
            ->where('document_key', RetainedDocumentKey::IncidentFinal)
            ->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('documents.retained.download', $submitted))
            ->assertOk();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('documents.retained.download', $final))
            ->assertForbidden();

        $this->actingAs($outsider->user()->firstOrFail())
            ->get(route('documents.retained.download', $submitted))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('documents.retained.preview', $final))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/viewer')
                ->where('pdfUrl', route('documents.retained.inline', $final))
                ->where('downloadUrl', route('documents.retained.download', $final)));
    }

    public function test_admin_documents_index_lists_retained_current_and_superseded(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'visit' => $visit, 'admin' => $admin] = $this->world();
        $manager = $supervisor->user()->firstOrFail();

        $incident = $this->submitIncident($dsp, $visit);
        $this->prepareCloseable($incident, $manager);
        $this->closeIncident($manager, $incident, 'First.');
        app(IncidentWorkflowService::class)->reopen($admin, $incident->refresh(), [
            'reopen_reason' => 'Amend.',
        ]);
        IncidentReview::query()->create([
            'incident_id' => $incident->id,
            'reviewer_user_id' => $manager->id,
            'note' => 'Post-reopen review.',
        ]);
        $incident->update([
            'finding' => 'Amended finding.',
            'cause_contributing_factors' => 'Amended cause.',
            'prevention_plan' => 'Amended plan.',
        ]);
        $this->closeIncident($manager, $incident->refresh(), 'Second.');

        $this->actingAs($admin)
            ->get(route('admin.documents.index', [
                'category' => 'incident_final_pdf',
                'source_area' => 'incidents',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/index')
                ->where('documents.total', 2)
                ->where('documents.data.0.status', fn (string $status): bool => str_contains($status, 'Retained'))
                ->where('documents.data.1.status', fn (string $status): bool => str_contains($status, 'superseded')));

        $this->actingAs($admin)
            ->get(route('admin.documents.index', [
                'category' => 'incident_submitted_pdf',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.total', 1)
                ->where('documents.data.0.status', 'Retained · current v1'));
    }

    public function test_no_automatic_backfill_for_legacy_formal_incidents(): void
    {
        ['dsp' => $dsp, 'visit' => $visit, 'admin' => $admin] = $this->world();

        $legacy = Incident::factory()->forVisit($visit)->submitted()->create([
            'reporter_employee_id' => $dsp->id,
            'submitted_at' => now(),
        ]);

        $this->assertSame(0, RetainedDocumentArtifact::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.documents.index', ['category' => 'incident_submitted_pdf']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.total', 1)
                ->where('documents.data.0.key', 'incident-submitted:'.$legacy->id));

        $this->actingAs($admin)
            ->get(route('documents.incident-submitted.pdf', $legacy))
            ->assertOk();

        $this->assertSame(0, RetainedDocumentArtifact::query()->count());
    }

    public function test_open_follow_ups_still_block_close_without_creating_final_snapshot(): void
    {
        ['supervisor' => $supervisor, 'dsp' => $dsp, 'visit' => $visit] = $this->world();
        $manager = $supervisor->user()->firstOrFail();

        $incident = $this->submitIncident($dsp, $visit);
        $this->prepareCloseable($incident, $manager);

        IncidentFollowUp::query()->create([
            'incident_id' => $incident->id,
            'action_type' => 'Retrain DSP',
            'assigned_employee_id' => $dsp->id,
            'due_on' => '2026-10-25',
            'status' => \App\Enums\IncidentFollowUpStatus::Open,
        ]);

        try {
            $this->closeIncident($manager, $incident);
            $this->fail('Expected closure validation failure.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('closure', $exception->errors());
        }

        $this->assertSame(0, RetainedDocumentArtifact::query()
            ->where('document_key', RetainedDocumentKey::IncidentFinal)
            ->count());
        $this->assertSame(1, RetainedDocumentArtifact::query()
            ->where('document_key', RetainedDocumentKey::IncidentSubmitted)
            ->count());
    }
}
