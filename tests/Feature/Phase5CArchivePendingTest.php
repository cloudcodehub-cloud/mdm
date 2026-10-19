<?php

namespace Tests\Feature;

use App\Enums\ClientStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\ClientAdminHistoryEvent;
use App\Models\Employee;
use App\Models\EmployeeAdminHistoryEvent;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SupervisorManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * MDM 03 Archive Pending: Admin archive is accepted when live work blocks safe archival,
 * current work stays intact, and the archive completes automatically afterward.
 */
class Phase5CArchivePendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function activeVisit(Client $client, Employee $dsp): Visit
    {
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'status' => ScheduledVisitStatus::InProgress,
        ]);

        return Visit::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'status' => VisitStatus::InProgress,
            'clocked_in_at' => now()->subHour(),
        ]);
    }

    private function futureVisit(Client $client, Employee $dsp, string $date = '2026-10-25'): ScheduledVisit
    {
        return ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => $date,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clockOutPayload(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Unavailable->value,
            'gps_fallback_reason' => GpsFallbackReason::GpsUnavailable->value,
            'handover_note' => 'Wrap-up complete for next DSP.',
        ];
    }

    private function archiveEmployee(User $admin, Employee $employee, ?string $reason = 'Offboarding'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->patch(route('employees.status', $employee), array_filter([
            'employment_status' => EmploymentStatus::Archived->value,
            'reason' => $reason,
        ]));
    }

    private function archiveClient(User $admin, Client $client, ?string $reason = 'Retention archive'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->patch(route('clients.status', $client), array_filter([
            'status' => ClientStatus::Archived->value,
            'reason' => $reason,
        ]));
    }

    public function test_admin_archive_of_dsp_in_active_visit_enters_archive_pending_and_keeps_work_intact(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $visit = $this->activeVisit($client, $dsp);
        $future = $this->futureVisit($client, $dsp);

        $this->archiveEmployee($admin, $dsp, 'Role elimination')
            ->assertRedirect(route('employees.show', $dsp));

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::ArchivePending, $dsp->employment_status);
        $this->assertNotSame(EmploymentStatus::Archived, $dsp->employment_status);
        $this->assertSame('Role elimination', $dsp->archive_pending_reason);
        $this->assertSame($admin->id, $dsp->archive_pending_by_user_id);
        $this->assertNotNull($dsp->archive_pending_at);
        $this->assertSame('active_visit', $dsp->archive_pending_blockers[0]['type']);
        $this->assertSame(EmploymentStatus::Active->value, $dsp->archive_pending_from_status);

        // Live work intact; future work NOT released until final archive.
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $future->refresh();
        $this->assertSame($dsp->id, $future->employee_id);
        $this->assertFalse($future->staffing_needed);

        // Not Employment Ended / Suspended: login stays, no new service eligibility.
        $this->assertTrue($dsp->user->fresh()->canAccessApplication());
        $this->assertFalse($dsp->isActiveDsp());
        $this->assertNull($dsp->terminated_on);

        $this->assertTrue(
            EmployeeAdminHistoryEvent::query()
                ->where('employee_id', $dsp->id)
                ->where('category', 'lifecycle')
                ->where('new_value', 'Archive Pending')
                ->where('reason', 'Role elimination')
                ->exists(),
        );
    }

    public function test_pending_dsp_can_finish_visit_then_archive_finalizes_and_future_visits_need_staffing(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $visit = $this->activeVisit($client, $dsp);
        $future = $this->futureVisit($client, $dsp);
        $past = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-10',
            'status' => ScheduledVisitStatus::Completed,
        ]);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();
        $this->assertSame(EmploymentStatus::ArchivePending, $dsp->fresh()->employment_status);

        $this->actingAs($dsp->user()->firstOrFail())
            ->patch(route('visits.notes', $visit), ['handover_note' => 'Notes while pending.'])
            ->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutPayload())
            ->assertRedirect();

        $this->assertSame(VisitStatus::Completed, $visit->fresh()->status);

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::Archived, $dsp->employment_status);
        $this->assertNull($dsp->archive_pending_at);
        $this->assertNull($dsp->archive_pending_blockers);
        $this->assertFalse($dsp->user->fresh()->canAccessApplication());

        $future->refresh();
        $this->assertNull($future->employee_id);
        $this->assertTrue($future->staffing_needed);
        $this->assertSame($dsp->id, $future->released_employee_id);
        $this->assertSame($dsp->id, $past->fresh()->employee_id);

        $this->assertTrue(
            EmployeeAdminHistoryEvent::query()
                ->where('employee_id', $dsp->id)
                ->where('new_value', 'Archived')
                ->where('old_value', 'Archive Pending')
                ->exists(),
        );
        $this->assertTrue(
            EmployeeAdminHistoryEvent::query()
                ->where('employee_id', $dsp->id)
                ->where('category', 'scheduling_impact')
                ->exists(),
        );
    }

    public function test_admin_archive_of_client_with_active_visit_is_pending_then_finalizes_after_clock_out(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $visit = $this->activeVisit($client, $dsp);
        $future = $this->futureVisit($client, $dsp);

        $this->archiveClient($admin, $client, 'Moved out of area')
            ->assertRedirect(route('clients.show', $client));

        $client->refresh();
        $this->assertSame(ClientStatus::ArchivePending, $client->status);
        $this->assertSame('Moved out of area', $client->archive_pending_reason);
        $this->assertSame($admin->id, $client->archive_pending_by_user_id);
        $this->assertFalse($client->status->allowsFutureScheduling());

        // Active visit untouched; future visit cancelled on Admin confirmation; history preserved.
        $this->assertSame(VisitStatus::InProgress, $visit->fresh()->status);
        $future->refresh();
        $this->assertSame(ScheduledVisitStatus::Cancelled, $future->status);
        $this->assertNotNull($future->cancelled_at);
        $this->assertSame($admin->id, $future->cancelled_by_user_id);

        $this->assertTrue(
            ClientAdminHistoryEvent::query()
                ->where('client_id', $client->id)
                ->where('new_value', 'Archive Pending')
                ->where('reason', 'Moved out of area')
                ->exists(),
        );

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('visits.clock-out', $visit), $this->clockOutPayload())
            ->assertRedirect();

        $client->refresh();
        $this->assertSame(ClientStatus::Archived, $client->status);
        $this->assertNull($client->archive_pending_at);
        $this->assertSame(VisitStatus::Completed, $visit->fresh()->status);
        $this->assertTrue(
            ClientAdminHistoryEvent::query()
                ->where('client_id', $client->id)
                ->where('old_value', 'Archive Pending')
                ->where('new_value', 'Archived')
                ->exists(),
        );
    }

    public function test_archive_without_blockers_is_immediate(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $dspFuture = $this->futureVisit($client, $dsp);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::Archived, $dsp->employment_status);
        $this->assertNull($dsp->archive_pending_at);
        $this->assertTrue($dspFuture->fresh()->staffing_needed);

        $otherDsp = Employee::factory()->dsp()->create();
        $idleClient = Client::factory()->create();
        $clientFuture = $this->futureVisit($idleClient, $otherDsp, '2026-10-26');

        $this->archiveClient($admin, $idleClient)->assertRedirect();

        $this->assertSame(ClientStatus::Archived, $idleClient->fresh()->status);
        $this->assertNull($idleClient->fresh()->archive_pending_at);
        $this->assertSame(ScheduledVisitStatus::Cancelled, $clientFuture->fresh()->status);
    }

    public function test_reason_is_required_for_archive_requests_and_nothing_changes_without_it(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $this->activeVisit($client, $dsp);

        $this->archiveEmployee($admin, $dsp, null)->assertSessionHasErrors('reason');
        $this->archiveClient($admin, $client, null)->assertSessionHasErrors('reason');

        $this->assertSame(EmploymentStatus::Active, $dsp->fresh()->employment_status);
        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_supervisor_cannot_initiate_archive_or_archive_pending(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $this->activeVisit($client, $dsp);
        $supervisorUser = $supervisor->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Archived->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->actingAs($supervisorUser)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::ArchivePending->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->actingAs($supervisorUser)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Archived->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->actingAs($supervisorUser)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::ArchivePending->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->assertSame(EmploymentStatus::Active, $dsp->fresh()->employment_status);
        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
    }

    public function test_supervisor_cannot_change_a_clients_pending_archive(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $this->activeVisit($client, $dsp);

        $this->archiveClient($admin, $client)->assertRedirect();
        $this->assertSame(ClientStatus::ArchivePending, $client->fresh()->status);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::Active->value,
            ])
            ->assertForbidden();

        $this->assertSame(ClientStatus::ArchivePending, $client->fresh()->status);
    }

    public function test_supervisor_with_caseload_is_archive_pending_until_reassigned_then_finalizes(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $replacement = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();

        $this->archiveEmployee($admin, $supervisor, 'Leaving agency')->assertRedirect();

        $supervisor->refresh();
        $this->assertSame(EmploymentStatus::ArchivePending, $supervisor->employment_status);
        $blockerTypes = collect($supervisor->archive_pending_blockers)->pluck('type')->all();
        $this->assertContains('supervisor_team', $blockerTypes);
        $this->assertContains('supervisor_caseload', $blockerTypes);
        $this->assertTrue($supervisor->user->fresh()->canAccessApplication());

        $service = app(SupervisorManagementService::class);

        // Partial coverage keeps it pending.
        $service->reassignTeam($supervisor, $replacement, [$dsp->id], $admin);
        $this->assertSame(EmploymentStatus::ArchivePending, $supervisor->fresh()->employment_status);

        // Once the caseload is covered it finalizes automatically; Admin does not repeat.
        $service->reassignCaseload($supervisor->fresh(), $replacement, [$client->id], $admin);

        $supervisor->refresh();
        $this->assertSame(EmploymentStatus::Archived, $supervisor->employment_status);
        $this->assertNull($supervisor->archive_pending_at);
        $this->assertSame($replacement->id, $client->fresh()->supervisor_id);
        $this->assertSame($replacement->id, $dsp->fresh()->supervisor_id);
        $this->assertFalse($supervisor->user->fresh()->canAccessApplication());
        $this->assertTrue(
            EmployeeAdminHistoryEvent::query()
                ->where('employee_id', $supervisor->id)
                ->where('old_value', 'Archive Pending')
                ->where('new_value', 'Archived')
                ->exists(),
        );
    }

    public function test_new_scheduling_is_blocked_while_archive_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $pendingDsp = Employee::factory()->dsp()->create();
        $busyClient = Client::factory()->create();
        $this->activeVisit($busyClient, $pendingDsp);
        $otherClient = Client::factory()->create();

        $this->archiveEmployee($admin, $pendingDsp)->assertRedirect();
        $this->assertFalse($pendingDsp->fresh()->isActiveDsp());

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $otherClient->id,
                'employee_id' => $pendingDsp->id,
                'service_date' => '2026-10-27',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');

        $pendingClient = Client::factory()->create();
        $otherDsp = Employee::factory()->dsp()->create();
        $this->activeVisit($pendingClient, $otherDsp);
        $this->archiveClient($admin, $pendingClient)->assertRedirect();
        $this->assertFalse($pendingClient->fresh()->status->allowsFutureScheduling());

        $freshDsp = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $pendingClient->id,
                'employee_id' => $freshDsp->id,
                'service_date' => '2026-10-27',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('client_id');
    }

    public function test_pending_dsp_cannot_clock_in_to_a_new_visit(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $this->activeVisit($client, $dsp);
        $later = ScheduledVisit::factory()->forClient(Client::factory()->create())->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '16:00:00',
            'ends_at' => '18:00:00',
        ]);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $later), [
                'location_method' => ClockInLocationMethod::GpsUnavailable->value,
                'location_status' => ClockInLocationStatus::Denied->value,
                'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
            ])
            ->assertForbidden();

        $this->assertSame(0, Visit::query()->where('scheduled_visit_id', $later->id)->count());
    }

    public function test_scheduled_safety_net_finalizes_when_blocker_cleared_without_clock_out(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $visit = $this->activeVisit($client, $dsp);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();
        $this->archiveClient($admin, $client)->assertRedirect();

        Artisan::call('mdm:finalize-archive-pending');
        $this->assertSame(EmploymentStatus::ArchivePending, $dsp->fresh()->employment_status);
        $this->assertSame(ClientStatus::ArchivePending, $client->fresh()->status);

        $visit->forceFill(['status' => VisitStatus::Completed, 'clocked_out_at' => now()])->save();

        Artisan::call('mdm:finalize-archive-pending');

        $this->assertSame(EmploymentStatus::Archived, $dsp->fresh()->employment_status);
        $this->assertSame(ClientStatus::Archived, $client->fresh()->status);
    }

    public function test_admin_can_cancel_a_pending_archive_request(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $this->activeVisit($client, $dsp);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Active->value,
            ])
            ->assertRedirect();

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::Active, $dsp->employment_status);
        $this->assertNull($dsp->archive_pending_at);
        $this->assertNull($dsp->archive_pending_reason);
        $this->assertTrue(
            EmployeeAdminHistoryEvent::query()
                ->where('employee_id', $dsp->id)
                ->where('old_value', 'Archive Pending')
                ->where('new_value', 'Active')
                ->exists(),
        );
    }

    public function test_archive_pending_cannot_be_set_directly_and_is_not_employment_ended(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::ArchivePending->value,
                'reason' => 'Direct attempt',
            ])
            ->assertSessionHasErrors('employment_status');

        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => ClientStatus::ArchivePending->value,
                'reason' => 'Direct attempt',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(EmploymentStatus::Active, $dsp->fresh()->employment_status);
        $this->assertSame(ClientStatus::Active, $client->fresh()->status);
        $this->assertNotSame(EmploymentStatus::EmploymentEnded, EmploymentStatus::ArchivePending);
    }

    public function test_employment_ended_dsp_archive_pending_does_not_restore_login(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->employmentEnded()->create();
        $client = Client::factory()->create();
        $this->activeVisit($client, $dsp);

        $this->assertFalse($dsp->user->fresh()->canAccessApplication());

        $this->archiveEmployee($admin, $dsp)->assertRedirect();

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::ArchivePending, $dsp->employment_status);
        $this->assertSame(EmploymentStatus::EmploymentEnded->value, $dsp->archive_pending_from_status);
        $this->assertFalse($dsp->user->fresh()->canAccessApplication());
    }

    public function test_impact_preview_and_show_pages_explain_archive_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $this->activeVisit($client, $dsp);

        $this->actingAs($admin)
            ->getJson(route('employees.lifecycle.preview', [
                'employee' => $dsp,
                'status' => EmploymentStatus::Archived->value,
            ]))
            ->assertOk()
            ->assertJsonPath('will_enter_archive_pending', true)
            ->assertJsonPath('archive_blockers.0.type', 'active_visit');

        $this->actingAs($admin)
            ->getJson(route('clients.lifecycle.preview', [
                'client' => $client,
                'status' => ClientStatus::Archived->value,
            ]))
            ->assertOk()
            ->assertJsonPath('will_enter_archive_pending', true);

        $this->archiveEmployee($admin, $dsp)->assertRedirect();
        $this->archiveClient($admin, $client)->assertRedirect();

        $this->actingAs($admin)
            ->get(route('employees.show', $dsp))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employee.employment_status', 'archive_pending')
                ->where('employee.employment_status_label', 'Archive Pending')
                ->has('employee.archive_pending.message')
            );

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('client.status', 'archive_pending')
                ->has('client.archive_pending.message')
            );

        $this->actingAs($admin)
            ->get(route('employees.index', ['employment_status' => 'archive_pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employees.data', fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => $row['id'] === $dsp->id,
                ))
            );
    }
}
