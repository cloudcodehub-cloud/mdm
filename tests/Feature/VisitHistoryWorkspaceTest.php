<?php

namespace Tests\Feature;

use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitHistoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_list_is_upcoming_and_completed_are_retrievable(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        $upcoming = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'status' => ScheduledVisitStatus::Scheduled,
            'service_date' => '2026-09-22',
        ]);
        $completed = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'status' => ScheduledVisitStatus::Completed,
            'service_date' => '2026-09-20',
        ]);
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'status' => ScheduledVisitStatus::Cancelled,
            'service_date' => '2026-09-19',
        ]);

        $this->actingAs($admin)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/index')
                ->where('filters.phase', 'upcoming')
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $upcoming->id)
            );

        $this->actingAs($admin)
            ->get(route('scheduled-visits.index', ['phase' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $completed->id)
            );

        $this->actingAs($admin)
            ->get(route('scheduled-visits.index', ['phase' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('visits.data', 3));
    }

    public function test_visit_history_is_role_scoped(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $outsider = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();

        $own = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'status' => ScheduledVisitStatus::Completed,
            'service_date' => '2026-09-18',
            'supervisor_id' => $supervisor->id,
        ]);
        $hidden = ScheduledVisit::factory()->forClient($otherClient)->forDsp($outsider)->create([
            'status' => ScheduledVisitStatus::Completed,
            'service_date' => '2026-09-18',
            'supervisor_id' => $otherSupervisor->id,
        ]);

        $supervisorUser = $supervisor->user()->firstOrFail();
        $dspUser = $dsp->user()->firstOrFail();
        $outsiderUser = $outsider->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->get(route('scheduled-visits.index', ['phase' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $own->id)
            );

        $this->actingAs($dspUser)
            ->get(route('scheduled-visits.index', ['phase' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $own->id)
            );

        $this->actingAs($outsiderUser)
            ->get(route('scheduled-visits.index', ['phase' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $hidden->id)
            );

        $this->actingAs($dspUser)
            ->get(route('scheduled-visits.show', $hidden))
            ->assertForbidden();
    }

    public function test_calendar_preserves_safe_scope_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'status' => ScheduledVisitStatus::Scheduled,
            'service_date' => '2026-09-22',
        ]);

        $this->actingAs($admin)
            ->get(route('scheduled-visits.calendar', [
                'client_id' => $client->id,
                'phase' => 'upcoming',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/calendar')
                ->where('filters.client_id', (string) $client->id)
                ->where('filters.status', ScheduledVisitStatus::Scheduled->value)
            );
    }
}
