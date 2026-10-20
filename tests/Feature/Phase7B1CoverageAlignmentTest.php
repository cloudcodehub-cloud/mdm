<?php

namespace Tests\Feature;

use App\Enums\DateFormat;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\TemporarySupervisorCoverage;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use App\Services\TemporarySupervisorCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 7B.1 — MDM 03 §10 deferred redistribution on coverage modify + selective Admin UI.
 */
class Phase7B1CoverageAlignmentTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_modify_defers_active_visit_removal_and_applies_after_visit_ends(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Employee::factory()->supervisor()->create();
        $covering = Employee::factory()->supervisor()->create();
        $clientActive = Client::factory()->forSupervisor($original)->create();
        $clientKeep = Client::factory()->forSupervisor($original)->create();
        $clientDrop = Client::factory()->forSupervisor($original)->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($original)->create();

        $this->actingAs($admin)
            ->post(route('supervisors.temporary-coverages.store', $original), [
                'covering_supervisor_id' => $covering->id,
                'mode' => 'selective',
                'client_ids' => [$clientActive->id, $clientKeep->id, $clientDrop->id],
                'employee_ids' => [$dsp->id],
                'starts_at' => '2026-10-20T00:00',
                'ends_at' => '2026-10-27T23:59',
            ])
            ->assertSessionHasNoErrors();

        $scheduled = ScheduledVisit::factory()->forClient($clientActive)->forDsp($dsp)->create([
            'supervisor_id' => $original->id,
            'service_date' => '2026-10-20',
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
        ]);

        $coverage = TemporarySupervisorCoverage::query()->firstOrFail();
        $coveringUser = $covering->user()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('temporary-supervisor-coverages.update', $coverage), [
                'mode' => 'selective',
                'client_ids' => [$clientKeep->id],
                'employee_ids' => [$dsp->id],
            ])
            ->assertSessionHasNoErrors();

        $coverage->refresh();
        $this->assertSame([(int) $clientKeep->id], array_map('intval', $coverage->client_ids ?? []));
        $this->assertSame([(int) $clientActive->id], array_map('intval', $coverage->deferred_remove_client_ids ?? []));
        $this->assertNotContains((int) $clientDrop->id, array_map('intval', $coverage->deferred_remove_client_ids ?? []));

        // Permanent ownership unchanged; deferred relationship still authorized.
        $this->assertSame($original->id, $clientActive->fresh()->supervisor_id);
        $this->assertTrue($coveringUser->can('view', $clientActive->fresh()));
        $this->assertTrue($coveringUser->can('view', $clientKeep->fresh()));
        $this->assertFalse($coveringUser->can('view', $clientDrop->fresh()));

        $visit->update(['status' => VisitStatus::Completed, 'clocked_out_at' => now()]);
        app(TemporarySupervisorCoverageService::class)->applyDeferredRemovalsAfterVisit($visit->fresh());

        $coverage->refresh();
        $this->assertNull($coverage->deferred_remove_client_ids);
        $this->assertFalse($coveringUser->fresh()->can('view', $clientActive->fresh()));
        $this->assertTrue($coveringUser->fresh()->can('view', $clientKeep->fresh()));
        $this->assertSame($original->id, $clientActive->fresh()->supervisor_id);
    }

    public function test_revoke_clears_deferred_access_immediately_without_rewriting_visit(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Employee::factory()->supervisor()->create();
        $covering = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($original)->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($original)->create();

        $this->actingAs($admin)
            ->post(route('supervisors.temporary-coverages.store', $original), [
                'covering_supervisor_id' => $covering->id,
                'mode' => 'selective',
                'client_ids' => [$client->id],
                'employee_ids' => [$dsp->id],
                'starts_at' => '2026-10-20T00:00',
                'ends_at' => '2026-10-27T23:59',
            ])
            ->assertSessionHasNoErrors();

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'supervisor_id' => $original->id,
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
        ]);

        $coverage = TemporarySupervisorCoverage::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('temporary-supervisor-coverages.update', $coverage), [
                'mode' => 'selective',
                'client_ids' => [$client->id],
                'employee_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        // Narrow DSP only — if DSP still in active visit, defer DSP removal.
        $coverage->refresh();
        $this->assertSame([(int) $dsp->id], array_map('intval', $coverage->deferred_remove_employee_ids ?? []));

        $coveringUser = $covering->user()->firstOrFail();
        $this->assertTrue($coveringUser->can('view', $dsp->fresh()));

        $this->actingAs($admin)
            ->post(route('temporary-supervisor-coverages.revoke', $coverage), [
                'revoke_reason' => 'Coverage ended early.',
            ])
            ->assertSessionHasNoErrors();

        $coverage->refresh();
        $this->assertTrue($coverage->isRevoked());
        $this->assertNull($coverage->deferred_remove_employee_ids);
        $this->assertFalse($coveringUser->fresh()->can('view', $client->fresh()));
        $this->assertFalse($coveringUser->fresh()->can('view', $dsp->fresh()));
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $this->assertSame($original->id, $visit->fresh()->scheduledVisit->supervisor_id);
    }

    public function test_admin_supervisor_show_exposes_selective_caseload_options(): void
    {
        $admin = User::factory()->admin()->create();
        $original = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($original)->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($original)->create();

        $this->actingAs($admin)
            ->get(route('supervisors.show', $original))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('supervisors/show')
                ->has('responsibilities.clients', 1)
                ->where('responsibilities.clients.0.id', $client->id)
                ->has('responsibilities.dsps', 1)
                ->where('responsibilities.dsps.0.id', $dsp->id)
                ->where('can.manage', true)
            );
    }
}
