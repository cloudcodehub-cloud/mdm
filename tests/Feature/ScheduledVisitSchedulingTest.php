<?php

namespace Tests\Feature;

use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduledVisitSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_list_filter_create_and_edit_scheduled_visits(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $maya = Employee::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $elena = Client::query()->where('last_name', 'Vasquez')->firstOrFail();
        $day = ShiftTemplate::query()->where('code', 'day_7_3')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('scheduled-visits.index', [
                'service_date' => '2026-09-11',
                'client_id' => $elena->id,
                'employee_id' => $maya->id,
                'status' => 'scheduled',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/index')
                ->where('can.create', true)
                ->has('visits.data', 1)
            );

        $this->actingAs($admin)
            ->from(route('scheduled-visits.create'))
            ->post(route('scheduled-visits.store'), [
                'client_id' => $elena->id,
                'employee_id' => $maya->id,
                'supervisor_id' => $maya->supervisor_id,
                'shift_template_id' => $day->id,
                'service_date' => '2026-09-17',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'notes' => 'Coverage added by admin.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $visit = ScheduledVisit::query()
            ->where('employee_id', $maya->id)
            ->whereDate('service_date', '2026-09-17')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('scheduled-visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/show')
                ->where('visit.service_type', 'Personal Care')
                ->where('can.update', true)
            );

        $this->actingAs($admin)
            ->put(route('scheduled-visits.update', $visit), [
                'client_id' => $elena->id,
                'employee_id' => $maya->id,
                'supervisor_id' => $maya->supervisor_id,
                'service_date' => '2026-09-17',
                'starts_at' => '08:00',
                'ends_at' => '14:00',
                'service_type' => 'Overnight support',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'timing_mode' => 'custom',
            ])
            ->assertRedirect(route('scheduled-visits.show', $visit));

        $visit->refresh();
        $this->assertNull($visit->shift_template_id);
        $this->assertFalse($visit->spansOvernight());
        $this->assertSame('2026-09-17 14:00:00', $visit->endsAtOn()->format('Y-m-d H:i:s'));
    }

    public function test_invalid_dsp_inactive_client_and_overlapping_windows_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherJob = Employee::factory()->create(['job_type' => JobType::Other, 'user_id' => null]);
        $inactiveDsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create([
            'employment_status' => EmploymentStatus::Inactive,
        ]);
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $inactiveClient = Client::factory()->forSupervisor($supervisor)->create([
            'status' => ClientStatus::Inactive,
        ]);
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-21',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $otherJob->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $inactiveDsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $inactiveClient->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('client_id');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-21',
                'starts_at' => '14:00:00',
                'ends_at' => '18:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('service_date');
    }

    public function test_overnight_visit_does_not_overlap_a_following_day_shift_that_starts_at_the_end_time(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->overnight()->create([
            'service_date' => '2026-09-21',
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '07:00:00',
                'ends_at' => '15:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertRedirect();

        $created = ScheduledVisit::query()
            ->where('employee_id', $dsp->id)
            ->whereDate('service_date', '2026-09-22')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame('07:00:00', substr((string) $created->starts_at, 0, 8));
    }

    public function test_supervisor_can_manage_only_visits_in_scope(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $ownVisit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $otherVisit = ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create();

        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/index')
                ->where('can.create', true)
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $ownVisit->id)
            );

        $this->actingAs($user)
            ->get(route('scheduled-visits.show', $otherVisit))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $otherClient->id,
                'employee_id' => $otherDsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('client_id');

        $this->actingAs($user)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00:00',
                'ends_at' => '13:00:00',
                'service_type' => 'Community Integration',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertRedirect();
    }

    public function test_dsp_can_only_view_own_scheduled_visits(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $otherVisit = ScheduledVisit::query()
            ->whereHas('employee', fn ($query) => $query->where('email', 'nina.brooks@mdm.test'))
            ->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/index')
                ->where('can.create', false)
                ->has('visits.data')
            );

        $this->actingAs($dsp)
            ->get(route('scheduled-visits.create'))
            ->assertForbidden();

        $this->actingAs($dsp)
            ->get(route('scheduled-visits.show', $otherVisit))
            ->assertForbidden();

        $ownVisit = ScheduledVisit::query()
            ->where('employee_id', $dsp->employee?->id)
            ->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('scheduled-visits.show', $ownVisit))
            ->assertOk();

        $this->actingAs($dsp)
            ->put(route('scheduled-visits.update', $ownVisit), [
                'client_id' => $ownVisit->client_id,
                'employee_id' => $ownVisit->employee_id,
                'service_date' => $ownVisit->service_date->toDateString(),
                'service_type' => 'Changed',
                'status' => ScheduledVisitStatus::Cancelled->value,
                'shift_template_id' => $ownVisit->shift_template_id,
            ])
            ->assertForbidden();
    }

    public function test_scheduled_visit_destroy_route_is_not_defined(): void
    {
        $this->assertFalse(Route::has('scheduled-visits.destroy'));
    }
}
