<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\ClientAdminHistoryCategory;
use App\Enums\ClientStatus;
use App\Enums\VisitStatus;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientAdminHistoryEvent;
use App\Models\ClientAuthorization;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\CarePlanSetupService;
use App\Services\ClientActiveVisitGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 5D — MDM 06 Decisions 06-18, 06-28, 06-29 and authorization management.
 */
class Phase5DMdm06AlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_client_note_correction_preserves_original_and_amended_in_history(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['notes' => 'Original continuity note']);

        $this->actingAs($admin)
            ->put(route('clients.update', $client), [
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'status' => ClientStatus::Active->value,
                'notes' => 'Amended continuity note',
                'notes_correction_reason' => 'Typo fix after review',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('Amended continuity note', $client->fresh()->notes);
        $this->assertTrue(
            ClientAdminHistoryEvent::query()
                ->where('client_id', $client->id)
                ->where('category', ClientAdminHistoryCategory::Note)
                ->where('old_value', 'Original continuity note')
                ->where('new_value', 'Amended continuity note')
                ->where('reason', 'Typo fix after review')
                ->exists(),
        );
    }

    public function test_client_note_correction_requires_reason_when_amending_existing_note(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create(['notes' => 'Keep this history']);

        $this->actingAs($admin)
            ->from(route('clients.edit', $client))
            ->put(route('clients.update', $client), [
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'status' => ClientStatus::Active->value,
                'notes' => 'Silently overwritten?',
            ])
            ->assertRedirect(route('clients.edit', $client))
            ->assertSessionHasErrors('notes_correction_reason');

        $this->assertSame('Keep this history', $client->fresh()->notes);
    }

    public function test_admin_can_create_and_update_authorization_with_history(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('clients.authorizations.store', $client), [
                'authorization_number' => 'AUTH-5D-001',
                'payer' => 'County Board',
                'service_type' => 'Personal Care',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'authorized_units' => 120,
                'unit' => AuthorizationUnit::Hour->value,
                'status' => AuthorizationStatus::Active->value,
            ])
            ->assertRedirect();

        $authorization = ClientAuthorization::query()
            ->where('authorization_number', 'AUTH-5D-001')
            ->firstOrFail();

        $this->assertTrue(
            ClientAdminHistoryEvent::query()
                ->where('client_id', $client->id)
                ->where('category', ClientAdminHistoryCategory::Authorization)
                ->where('summary', 'like', 'Authorization created%')
                ->exists(),
        );

        $this->actingAs($admin)
            ->patch(route('clients.authorizations.update', [$client, $authorization]), [
                'authorization_number' => 'AUTH-5D-001',
                'payer' => 'County Board',
                'service_type' => 'Personal Care',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-06-30',
                'authorized_units' => 80,
                'unit' => AuthorizationUnit::Hour->value,
                'status' => AuthorizationStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame('80.00', $authorization->fresh()->authorized_units);
        $this->assertTrue(
            ClientAdminHistoryEvent::query()
                ->where('client_id', $client->id)
                ->where('category', ClientAdminHistoryCategory::Authorization)
                ->where('summary', 'like', 'Authorization updated%')
                ->exists(),
        );
    }

    public function test_supervisor_cannot_create_authorization(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('clients.authorizations.store', $client), [
                'authorization_number' => 'AUTH-DENY',
                'payer' => 'County Board',
                'service_type' => 'Personal Care',
                'starts_on' => '2026-01-01',
                'authorized_units' => 10,
                'unit' => AuthorizationUnit::Hour->value,
                'status' => AuthorizationStatus::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_service_and_authorization_changes_warn_but_do_not_end_active_visit(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $service = CareService::query()->first() ?? CareService::factory()->create();

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'status' => \App\Enums\ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
        ]);

        $warning = app(ClientActiveVisitGuard::class)->warningPayload($client);
        $this->assertNotNull($warning);
        $this->assertSame($visit->id, $warning['id']);

        app(CarePlanSetupService::class)->syncServices($client, [$service->id]);
        $this->assertSame(
            'An active visit is in progress. This change does not automatically end or invalidate that visit. MDM 05 remains responsible for visit completion.',
            session('active_visit_service_warning'),
        );
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);

        $this->actingAs($admin)
            ->post(route('clients.authorizations.store', $client), [
                'authorization_number' => 'AUTH-ACTIVE-VISIT',
                'payer' => 'County Board',
                'service_type' => 'Personal Care',
                'starts_on' => '2026-01-01',
                'authorized_units' => 40,
                'unit' => AuthorizationUnit::Hour->value,
                'status' => AuthorizationStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
    }

    public function test_dsp_qualification_remains_compliance_hard_stop_only(): void
    {
        // Decision 06-28: no service-specific DSP qualification matching engine.
        $this->assertFileDoesNotExist(app_path('Services/DspQualificationService.php'));
        $this->assertFileDoesNotExist(app_path('Services/StaffingEligibilityService.php'));
        $this->assertTrue(class_exists(\App\Services\ComplianceEligibilityService::class));
    }
}
