<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\Role;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeAdminHistoryEvent;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\EmployeeLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3CEmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_inactive_and_terminated_statuses_map_via_factory_aliases(): void
    {
        $suspended = Employee::factory()->dsp()->inactive()->create();
        $ended = Employee::factory()->dsp()->terminated()->create();

        $this->assertSame(EmploymentStatus::Suspended, $suspended->employment_status);
        $this->assertTrue($suspended->employment_status->allowsLogin());
        $this->assertFalse($suspended->employment_status->allowsService());

        $this->assertSame(EmploymentStatus::EmploymentEnded, $ended->employment_status);
        $this->assertFalse($ended->employment_status->allowsLogin());
        $this->assertFalse($ended->isActiveDsp());
    }

    public function test_suspended_dsp_keeps_login_but_cannot_be_scheduled(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $user = $dsp->user;
        $this->assertNotNull($user);

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Suspended->value,
                'reason' => 'Investigation hold',
            ])
            ->assertRedirect(route('employees.show', $dsp));

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::Suspended, $dsp->employment_status);
        $this->assertSame('Investigation hold', $dsp->status_reason);
        $this->assertTrue($dsp->employment_status->allowsLogin());
        $this->assertFalse($dsp->isActiveDsp());
        $this->assertTrue($user->fresh()->canAccessApplication());
    }

    public function test_employment_ended_releases_future_visits_to_staffing_needed_and_blocks_login(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $user = $dsp->user;
        $this->assertNotNull($user);

        $future = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDays(3)->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $past = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->subDays(3)->toDateString(),
            'status' => ScheduledVisitStatus::Completed,
        ]);

        $activeVisit = Visit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'scheduled_visit_id' => ScheduledVisit::factory()->create([
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => now()->toDateString(),
                'status' => ScheduledVisitStatus::InProgress,
            ])->id,
            'status' => VisitStatus::InProgress,
        ]);

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::EmploymentEnded->value,
                'reason' => 'Resignation effective today',
                'terminated_on' => now()->toDateString(),
            ])
            ->assertRedirect(route('employees.show', $dsp));

        $future->refresh();
        $past->refresh();
        $activeVisit->refresh();
        $dsp->refresh();

        $this->assertSame(EmploymentStatus::EmploymentEnded, $dsp->employment_status);
        $this->assertNull($future->employee_id);
        $this->assertTrue($future->staffing_needed);
        $this->assertSame($dsp->id, $future->released_employee_id);
        $this->assertSame($dsp->id, $past->employee_id);
        $this->assertFalse($past->staffing_needed);
        $this->assertSame(VisitStatus::InProgress, $activeVisit->status);
        $this->assertSame($dsp->id, $activeVisit->employee_id);

        $this->assertDatabaseHas('employee_admin_history_events', [
            'employee_id' => $dsp->id,
            'category' => 'lifecycle',
            'new_value' => 'Employment Ended',
        ]);

        $this->assertFalse($dsp->employment_status->allowsLogin());
        $this->assertFalse($user->fresh()->canAccessApplication());
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Dsp->value]);
        $this->assertDatabaseHas('employees', [
            'id' => $dsp->id,
            'user_id' => $user->id,
            'employment_status' => EmploymentStatus::EmploymentEnded->value,
        ]);
    }

    public function test_archive_requires_reason_and_preserves_history(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->employmentEnded()->create();

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Archived->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Archived->value,
                'reason' => 'Offboarded from active directories',
            ])
            ->assertRedirect(route('employees.show', $dsp));

        $dsp->refresh();
        $this->assertSame(EmploymentStatus::Archived, $dsp->employment_status);
        $this->assertGreaterThan(0, EmployeeAdminHistoryEvent::query()->where('employee_id', $dsp->id)->count());

        $this->actingAs($admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employees.data', fn ($rows) => collect($rows)->every(
                    fn (array $row): bool => $row['id'] !== $dsp->id,
                ))
            );

        $this->actingAs($admin)
            ->get(route('employees.index', ['employment_status' => 'archived']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employees.data', fn ($rows) => collect($rows)->contains(
                    fn (array $row): bool => $row['id'] === $dsp->id,
                ))
            );
    }

    public function test_supervisor_cannot_change_lifecycle_and_admin_sees_impact_preview(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->create();

        ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDay()->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Suspended->value,
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('employees.lifecycle.preview', [
                'employee' => $dsp,
                'status' => EmploymentStatus::Suspended->value,
            ]))
            ->assertOk()
            ->assertJsonPath('future_scheduled_count', 1)
            ->assertJsonPath('will_release_to_staffing_needed', true)
            ->assertJsonPath('target_status', EmploymentStatus::Suspended->value);
    }

    public function test_reactivation_from_employment_ended_restores_login_without_reassigning_visits(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $user = $dsp->user;
        $this->assertNotNull($user);

        $visit = ScheduledVisit::factory()->create([
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => now()->addDays(2)->toDateString(),
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        app(EmployeeLifecycleService::class)->transition($dsp, $admin, [
            'employment_status' => EmploymentStatus::EmploymentEnded->value,
            'reason' => 'Ended',
            'effective_on' => now()->toDateString(),
        ]);

        $visit->refresh();
        $this->assertTrue($visit->staffing_needed);
        $this->assertNull($visit->employee_id);

        app(EmployeeLifecycleService::class)->transition($dsp->fresh(), $admin, [
            'employment_status' => EmploymentStatus::Active->value,
        ]);

        $dsp->refresh();
        $visit->refresh();

        $this->assertSame(EmploymentStatus::Active, $dsp->employment_status);
        $this->assertTrue($dsp->employment_status->allowsLogin());
        $this->assertNull($visit->employee_id);
        $this->assertTrue($visit->staffing_needed);
        $this->assertTrue($user->fresh()->canAccessApplication());
    }

    public function test_invalid_transition_from_archived_to_suspended_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->archived()->create();

        $this->actingAs($admin)
            ->patch(route('employees.status', $dsp), [
                'employment_status' => EmploymentStatus::Suspended->value,
                'reason' => 'Invalid jump',
            ])
            ->assertSessionHasErrors('employment_status');

        $this->assertSame(EmploymentStatus::Archived, $dsp->fresh()->employment_status);
    }

    public function test_show_page_includes_lifecycle_controls_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create([
            'job_type' => JobType::Dsp,
        ]);

        $this->actingAs($admin)
            ->get(route('employees.show', $dsp))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('employees/show')
                ->where('can.manage_lifecycle', true)
                ->has('lifecycle_previews.suspended')
                ->has('lifecycle_previews.employment_ended')
                ->has('admin_history')
            );
    }
}
