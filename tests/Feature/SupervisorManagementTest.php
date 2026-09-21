<?php

namespace Tests\Feature;

use App\Enums\JobType;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupervisorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_promote_eligible_active_employee_without_changing_job_title(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create([
            'job_title' => 'Direct Support Professional',
        ]);
        $employeeId = $employee->id;

        $this->actingAs($admin)
            ->get(route('supervisors.create', ['search' => $employee->last_name]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supervisors/create')
                ->has('employees.data', 1)
                ->where('employees.data.0.id', $employeeId)
            );

        $this->actingAs($admin)
            ->post(route('supervisors.store'), [
                'employee_id' => $employeeId,
            ])
            ->assertRedirect(route('supervisors.show', $employee));

        $employee->refresh();
        $user = $employee->user()->firstOrFail();

        $this->assertSame($employeeId, $employee->id);
        $this->assertSame(JobType::Supervisor, $employee->job_type);
        $this->assertSame('Direct Support Professional', $employee->job_title);
        $this->assertSame(Role::Supervisor, $user->role);
        $this->assertSame('promoted', $employee->role_change_history[0]['action'] ?? null);
        $this->assertSame(Role::Dsp->value, $employee->role_change_history[0]['previous_role'] ?? null);
        $this->assertSame($admin->id, $employee->role_change_history[0]['actor_user_id'] ?? null);
    }

    public function test_inactive_and_terminated_employees_cannot_be_promoted(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = Employee::factory()->dsp()->inactive()->create();
        $terminated = Employee::factory()->dsp()->terminated()->create();

        $this->actingAs($admin)
            ->post(route('supervisors.store'), ['employee_id' => $inactive->id])
            ->assertSessionHasErrors('employee_id');

        $this->actingAs($admin)
            ->post(route('supervisors.store'), ['employee_id' => $terminated->id])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(JobType::Dsp, $inactive->fresh()->job_type);
        $this->assertSame(JobType::Dsp, $terminated->fresh()->job_type);
    }

    public function test_unlinked_and_existing_supervisors_cannot_be_promoted(): void
    {
        $admin = User::factory()->admin()->create();
        $unlinked = Employee::factory()->create(['job_type' => JobType::Dsp]);
        $supervisor = Employee::factory()->supervisor()->create();

        $this->actingAs($admin)
            ->post(route('supervisors.store'), ['employee_id' => $unlinked->id])
            ->assertSessionHasErrors('employee_id');

        $this->actingAs($admin)
            ->post(route('supervisors.store'), ['employee_id' => $supervisor->id])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_supervisor_and_dsp_cannot_promote(): void
    {
        $employee = Employee::factory()->dsp()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('supervisors.create'))
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('supervisors.store'), ['employee_id' => $employee->id])
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('supervisors.store'), ['employee_id' => $employee->id])
            ->assertForbidden();

        $this->assertSame(JobType::Dsp, $employee->fresh()->job_type);
    }

    public function test_admin_can_revoke_supervisor_with_no_active_responsibilities(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create([
            'job_title' => 'Program Supervisor',
        ]);
        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('supervisors.revoke', $supervisor))
            ->assertRedirect(route('supervisors.index'));

        $supervisor->refresh();
        $user->refresh();

        $this->assertSame(JobType::Dsp, $supervisor->job_type);
        $this->assertSame('Program Supervisor', $supervisor->job_title);
        $this->assertSame(Role::Dsp, $user->role);
        $this->assertSame($supervisor->id, Employee::query()->findOrFail($supervisor->id)->id);
        $this->assertSame('revoked', $supervisor->role_change_history[0]['action'] ?? null);

        $this->actingAs($user)
            ->get(route('operations.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('supervisors.index'))
            ->assertForbidden();
    }

    public function test_supervisor_with_team_cannot_be_revoked_without_replacement(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        Employee::factory()->dsp()->forSupervisor($supervisor)->create();

        $this->actingAs($admin)
            ->post(route('supervisors.revoke', $supervisor))
            ->assertSessionHasErrors('replacement_employee_id');

        $this->assertSame(JobType::Supervisor, $supervisor->fresh()->job_type);
    }

    public function test_reassignment_transfers_current_scope_and_leaves_historical_visits(): void
    {
        $admin = User::factory()->admin()->create();
        $from = Employee::factory()->supervisor()->create();
        $to = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($from)->create();
        $client = Client::factory()->forSupervisor($from)->create();
        $visit = ScheduledVisit::factory()
            ->forClient($client)
            ->forDsp($dsp)
            ->create(['supervisor_id' => $from->id]);
        $recordedVisit = Visit::factory()->forScheduledVisit($visit)->create();

        $this->actingAs($from->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertOk();
        $this->actingAs($from->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('supervisors.team', $from), [
                'employee_ids' => [$dsp->id],
                'replacement_employee_id' => $to->id,
            ])
            ->assertRedirect(route('supervisors.show', $from));

        $this->actingAs($admin)
            ->post(route('supervisors.caseload', $from), [
                'client_ids' => [$client->id],
                'replacement_employee_id' => $to->id,
            ])
            ->assertRedirect(route('supervisors.show', $from));

        $this->assertSame($to->id, $dsp->fresh()->supervisor_id);
        $this->assertSame($to->id, $client->fresh()->supervisor_id);
        $this->assertSame($from->id, $visit->fresh()->supervisor_id);
        $this->assertSame($visit->id, $recordedVisit->fresh()->scheduled_visit_id);

        $this->actingAs($from->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertForbidden();
        $this->actingAs($from->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertForbidden();

        $this->actingAs($to->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertOk();
        $this->actingAs($to->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk();
    }

    public function test_revoke_with_replacement_transfers_responsibilities(): void
    {
        $admin = User::factory()->admin()->create();
        $from = Employee::factory()->supervisor()->create();
        $to = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($from)->create();
        $client = Client::factory()->forSupervisor($from)->create();
        $fromUser = $from->user()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('supervisors.revoke', $from), [
                'replacement_employee_id' => $to->id,
            ])
            ->assertRedirect(route('supervisors.index'));

        $this->assertSame($to->id, $dsp->fresh()->supervisor_id);
        $this->assertSame($to->id, $client->fresh()->supervisor_id);
        $this->assertSame(JobType::Dsp, $from->fresh()->job_type);
        $this->assertSame(Role::Dsp, $fromUser->fresh()->role);

        $this->actingAs($fromUser->fresh())
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_pending_availability_follows_dsp_team_and_stays_available_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $from = Employee::factory()->supervisor()->create();
        $to = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($from)->create();
        $availability = DspAvailabilityRequest::factory()->create([
            'employee_id' => $dsp->id,
            'requested_by_user_id' => $dsp->user_id,
        ]);
        $timeOff = EmployeeTimeOff::factory()->pending()->create([
            'employee_id' => $dsp->id,
            'requested_by_user_id' => $dsp->user_id,
        ]);

        $this->actingAs($admin)
            ->post(route('supervisors.team', $from), [
                'employee_ids' => [$dsp->id],
                'replacement_employee_id' => $to->id,
            ])
            ->assertRedirect();

        $this->actingAs($from->user()->firstOrFail())
            ->patch(route('availability-requests.approve', $availability))
            ->assertForbidden();

        $this->actingAs($to->user()->firstOrFail())
            ->patch(route('availability-requests.approve', $availability))
            ->assertRedirect();

        $this->actingAs($from->user()->firstOrFail())
            ->patch(route('time-off.approve', $timeOff))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('time-off.approve', $timeOff))
            ->assertRedirect();
    }

    public function test_pending_attendance_corrections_remain_admin_reviewable_after_revoke(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create();
        $correction = AttendanceCorrection::factory()->create([
            'scheduled_visit_id' => $scheduled->id,
            'requested_by_user_id' => $supervisor->user_id,
        ]);

        $this->actingAs($admin)
            ->post(route('supervisors.revoke', $supervisor))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('attendance.corrections.approve', $correction))
            ->assertRedirect();
    }

    public function test_non_admin_management_urls_are_forbidden(): void
    {
        $from = Employee::factory()->supervisor()->create();
        $to = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($from)->create();
        $client = Client::factory()->forSupervisor($from)->create();
        $other = Employee::factory()->dsp()->create();

        foreach ([$from->user()->firstOrFail(), $other->user()->firstOrFail()] as $user) {
            $this->actingAs($user)
                ->get(route('supervisors.create'))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('supervisors.store'), ['employee_id' => $other->id])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('supervisors.team', $from), [
                    'employee_ids' => [$dsp->id],
                    'replacement_employee_id' => $to->id,
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('supervisors.caseload', $from), [
                    'client_ids' => [$client->id],
                    'replacement_employee_id' => $to->id,
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('supervisors.revoke', $from), [
                    'replacement_employee_id' => $to->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame($from->id, $dsp->fresh()->supervisor_id);
        $this->assertSame(JobType::Supervisor, $from->fresh()->job_type);
    }
}
