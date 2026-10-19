<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\ClientStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitStatus;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\AuthorizationUsageService;
use App\Services\CarePlanReviewService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase2CLifecycleAuthReviewTest extends TestCase
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

    public function test_supervisor_can_pause_client_with_impact_preview_and_blocks_future_scheduling(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->getJson(route('clients.lifecycle.preview', [
                'client' => $client,
                'status' => ClientStatus::Paused->value,
            ]))
            ->assertOk()
            ->assertJsonPath('future_scheduled_count', 1)
            ->assertJsonPath('target_status', 'paused');

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Paused->value,
                'reason' => 'Hospitalization pause',
            ])
            ->assertRedirect();

        $client->refresh();
        $this->assertSame(ClientStatus::Paused, $client->status);
        $this->assertSame('Hospitalization pause', $client->status_reason);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-10-22',
                'service_type' => 'Residential Habilitation',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertSessionHasErrors('client_id');
    }

    public function test_unassigned_supervisor_cannot_change_lifecycle(): void
    {
        $owner = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($owner)->create();

        $this->actingAs($other->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Paused->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();
    }

    public function test_only_admin_can_archive_client(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Archived->value,
            ])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Archived->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Archived->value,
                'reason' => 'Retention archive',
            ])
            ->assertRedirect();

        $this->assertSame(ClientStatus::Archived, $client->fresh()->status);
    }

    public function test_pause_does_not_invalidate_active_visit(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Denied,
            'clock_in_unavailable_reason' => GpsFallbackReason::PermissionDenied->value,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Paused->value,
                'reason' => 'Pause during active visit',
            ])
            ->assertRedirect();

        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $preview = app(\App\Services\ClientLifecycleService::class)
            ->impactPreview($client->fresh(), ClientStatus::Paused);
        $this->assertNotNull($preview['active_visit']);
    }

    public function test_authorization_usage_tracks_actual_projected_and_remaining(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $auth = ClientAuthorization::factory()->forClient($client)->create([
            'service_type' => 'Residential Habilitation',
            'authorized_units' => '16.00',
            'unit' => AuthorizationUnit::Hour,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
            'status' => AuthorizationStatus::Active,
        ]);

        $completed = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-10',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Completed,
            'starts_at' => '09:00',
            'ends_at' => '13:00',
            'supervisor_id' => $supervisor->id,
        ]);
        Visit::factory()->create([
            'scheduled_visit_id' => $completed->id,
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_type' => 'Residential Habilitation',
            'status' => VisitStatus::Completed,
            'clocked_in_at' => Carbon::parse('2026-10-10 09:00:00'),
            'clocked_out_at' => Carbon::parse('2026-10-10 13:00:00'),
            'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
            'clock_in_location_status' => ClockInLocationStatus::Denied,
            'clock_in_unavailable_reason' => GpsFallbackReason::PermissionDenied->value,
        ]);

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'supervisor_id' => $supervisor->id,
        ]);

        // Cancelled visits must not count toward projected usage.
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-21',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Cancelled,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $summary = app(AuthorizationUsageService::class)->summarize($auth);

        $this->assertSame(4.0, $summary['actual_used']);
        $this->assertSame(8.0, $summary['projected_future']);
        $this->assertSame(12.0, $summary['projected_total']);
        $this->assertSame(4.0, $summary['remaining']);
        $this->assertFalse($summary['hard_block']);
    }

    public function test_expired_authorization_blocks_future_scheduling(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientAuthorization::factory()->forClient($client)->create([
            'service_type' => 'Residential Habilitation',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-09-30',
            'status' => AuthorizationStatus::Expired,
            'authorized_units' => '40.00',
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-10-22',
                'service_type' => 'Residential Habilitation',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertSessionHasErrors('service_type');
    }

    public function test_care_plan_review_warning_and_deduplicated_notification(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'review_on' => '2026-10-01',
            'starts_on' => '2026-09-01',
        ]);

        $state = app(CarePlanReviewService::class)->reviewState($plan);
        $this->assertTrue($state['is_overdue']);
        $this->assertNotNull($state['warning']);

        $service = app(CarePlanReviewService::class);
        $first = $service->dispatchDue();
        $second = $service->dispatchDue();

        $this->assertGreaterThan(0, $first);
        $this->assertGreaterThan(0, $second);

        $this->assertSame(
            1,
            InAppNotification::query()
                ->where('user_id', $supervisor->user()->firstOrFail()->id)
                ->where('source_key', sprintf('care-plan.%d.review.2026-10-01.overdue', $plan->id))
                ->count(),
        );
    }

    public function test_returning_discharged_client_to_active_restores_scheduling(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->discharged()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(ClientStatus::Active, $client->fresh()->status);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-10-25',
                'service_type' => 'Residential Habilitation',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'starts_at' => '09:00',
                'ends_at' => '12:00',
            ])
            ->assertRedirect();
    }
}
