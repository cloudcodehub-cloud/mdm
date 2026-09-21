<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6BAccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unlinked_dsp_cannot_log_in(): void
    {
        $user = User::factory()->dsp()->create();

        $this->assertNull($user->employee);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Dsp->value]);
    }

    public function test_unlinked_supervisor_cannot_log_in(): void
    {
        $user = User::factory()->supervisor()->create();

        $this->assertNull($user->employee);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Supervisor->value]);
    }

    public function test_acting_as_unlinked_dsp_or_supervisor_cannot_retain_protected_access(): void
    {
        $dsp = User::factory()->dsp()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($dsp)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unlinked_dsp_and_supervisor_cannot_reach_messages(): void
    {
        $dsp = User::factory()->dsp()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($dsp)
            ->get(route('messages.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($supervisor)
            ->get(route('messages.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_without_employee_and_linked_active_accounts_remain_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertNull($admin->employee);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
        $this->post(route('logout'));

        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->post(route('login.store'), [
            'email' => $supervisor->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($supervisor);
        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $dsp->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($dsp);

        $this->actingAs($supervisor)->get(route('messages.index'))->assertOk();
        $this->actingAs($dsp)->get(route('messages.index'))->assertOk();
        $this->actingAs($admin)->get(route('messages.index'))->assertOk();
    }

    public function test_dsp_cannot_open_own_or_other_employee_hr_records(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('employees.show', $dsp))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('employees.photo', $dsp))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('employees.show', $other))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('employees.photo', $other))
            ->assertForbidden();
    }

    public function test_supervisor_and_admin_employee_record_access_remains_valid(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $report = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $other = Employee::factory()->dsp()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.show', $report))
            ->assertOk();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.show', $supervisor))
            ->assertOk();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.show', $other))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('employees.show', $report))
            ->assertOk();
    }

    public function test_assigned_dsp_client_detail_omits_billing_and_other_dsp_data(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create([
            'notes' => 'Primary coverage notes',
        ]);
        ClientDspAssignment::factory()->forDsp($other)->forClient($client)->create([
            'notes' => 'Other DSP confidential assignment note',
        ]);
        ClientAuthorization::factory()->forClient($client)->create([
            'authorization_number' => 'AUTH-SECRET',
            'payer' => 'Ohio Medicaid',
        ]);

        $ownVisit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
        ]);
        $otherVisit = ScheduledVisit::factory()->forClient($client)->forDsp($other)->create([
            'service_date' => '2026-09-10',
        ]);

        $otherRecorded = Visit::factory()->forScheduledVisit($otherVisit)->create([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now()->subDay(),
        ]);
        VisitTask::factory()->create([
            'visit_id' => $otherRecorded->id,
            'title' => 'Other DSP meal note',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Other DSP private handover.',
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('client.id', $client->id)
                ->has('authorizations', 0)
                ->has('assignments', 0)
                ->has('dspOptions', 0)
                ->has('scheduledVisits', 1)
                ->where('scheduledVisits.0.id', $ownVisit->id)
                ->where('care_overview.history', [])
                ->missing('client.authorizations'));

        $unassigned = Client::factory()->create();
        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $unassigned))
            ->assertForbidden();
    }

    public function test_admin_and_supervisor_client_detail_still_includes_full_caseload_payload(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create([
            'notes' => 'Coverage notes',
        ]);
        ClientAuthorization::factory()->forClient($client)->create([
            'authorization_number' => 'AUTH-KEEP',
        ]);
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();

        $admin = User::factory()->admin()->create();

        foreach ([$admin, $supervisor->user()->firstOrFail()] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('clients.show', $client))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('clients/show')
                    ->has('authorizations', 1)
                    ->where('authorizations.0.authorization_number', 'AUTH-KEEP')
                    ->has('assignments', 1)
                    ->where('assignments.0.notes', 'Coverage notes')
                    ->has('scheduledVisits', 1));
        }
    }

    public function test_dsp_visit_care_history_excludes_other_dsp_notes(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $ownScheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-12',
        ]);
        $ownVisit = Visit::factory()->forScheduledVisit($ownScheduled)->create();
        VisitTask::factory()->create([
            'visit_id' => $ownVisit->id,
            'title' => 'Own DSP task',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Own handover.',
            'completed_at' => now()->subHour(),
        ]);

        $otherScheduled = ScheduledVisit::factory()->forClient($client)->forDsp($other)->create([
            'service_date' => '2026-09-11',
        ]);
        $otherVisit = Visit::factory()->forScheduledVisit($otherScheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now()->subDay(),
        ]);
        VisitTask::factory()->create([
            'visit_id' => $otherVisit->id,
            'title' => 'Other DSP task',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Must stay hidden from this DSP.',
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $ownVisit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->has('care_history', 1)
                ->where('care_history.0.task_title', 'Own DSP task')
                ->where('care_history.0.note', 'Own handover.'));
    }

    public function test_account_self_deletion_is_forbidden_and_preserves_records(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisorEmployee = Employee::factory()->supervisor()->create();
        $dspEmployee = Employee::factory()->dsp()->create();
        $supervisor = $supervisorEmployee->user()->firstOrFail();
        $dsp = $dspEmployee->user()->firstOrFail();

        $scheduled = ScheduledVisit::factory()->forDsp($dspEmployee)->create();
        $correction = AttendanceCorrection::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'requested_by_user_id' => $dsp->id,
        ]);

        foreach ([$admin, $supervisor, $dsp] as $user) {
            $this->actingAs($user)
                ->delete(route('profile.destroy'), ['password' => 'password'])
                ->assertForbidden();

            $this->assertAuthenticatedAs($user);
            $this->assertNotNull($user->fresh());
        }

        $this->assertDatabaseHas('employees', [
            'id' => $dspEmployee->id,
            'user_id' => $dsp->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $supervisorEmployee->id,
            'user_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'requested_by_user_id' => $dsp->id,
        ]);
        $this->assertDatabaseHas('scheduled_visits', ['id' => $scheduled->id]);
    }

    public function test_profile_settings_page_does_not_offer_account_deletion(): void
    {
        $user = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Delete account');
    }
}
