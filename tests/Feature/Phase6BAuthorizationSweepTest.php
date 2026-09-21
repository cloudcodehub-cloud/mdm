<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Conversation;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6BAuthorizationSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dsp_direct_urls_are_forbidden_for_management_modules(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($user)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($user)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($user)->get(route('clients.setup.edit', $client))->assertForbidden();
        $this->actingAs($user)->post(route('clients.setup.update', $client), [
            'service_ids' => [],
            'tasks' => [],
        ])->assertForbidden();
        $this->actingAs($user)->get(route('care-services.index'))->assertForbidden();
        $this->actingAs($user)->get(route('scheduled-visits.create'))->assertForbidden();
        $this->actingAs($user)->get(route('operations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('visit-exceptions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('supervisors.index'))->assertForbidden();
        $this->actingAs($user)->get(route('compliance.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.show', ReportType::PayrollHours->value))->assertForbidden();
        $this->actingAs($user)->get(route('reports.download', ReportType::PayrollHours->value))->assertForbidden();
        $this->actingAs($user)->get(route('availability-requests.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.general.edit'))->assertForbidden();
        $this->actingAs($user)->patch(route('settings.general.update'), [
            'organization_name' => 'Should not save',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => '24',
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ])->assertForbidden();
    }

    public function test_dsp_may_open_operational_own_surfaces(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('clients.show', $client))->assertOk();
        $this->actingAs($user)->get(route('scheduled-visits.index'))->assertOk();
        $this->actingAs($user)->get(route('scheduled-visits.show', $scheduled))->assertOk();
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();
        $this->actingAs($user)->get(route('attendance.show', $scheduled))->assertOk();
        $this->actingAs($user)->get(route('messages.index'))->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->actingAs($user)->get(route('my-availability.index'))->assertOk();
        $this->actingAs($user)->get(route('announcements.index'))->assertOk();
    }

    public function test_supervisor_cannot_open_admin_only_direct_urls(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($user)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($user)->get(route('care-services.index'))->assertForbidden();
        $this->actingAs($user)->get(route('supervisors.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.general.edit'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.download', ReportType::PayrollHours->value))->assertForbidden();
        $this->actingAs($user)->get(route('my-availability.index'))->assertForbidden();
    }

    public function test_supervisor_cannot_open_out_of_caseload_records_by_direct_url(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $ownClient = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($other)->create();
        $ownDsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($other)->create();
        $otherVisit = ScheduledVisit::factory()->forClient($otherClient)->forDsp($otherDsp)->create();
        $user = $supervisor->user()->firstOrFail();

        $this->actingAs($user)->get(route('clients.show', $ownClient))->assertOk();
        $this->actingAs($user)->get(route('clients.show', $otherClient))->assertForbidden();
        $this->actingAs($user)->get(route('employees.show', $ownDsp))->assertOk();
        $this->actingAs($user)->get(route('employees.show', $otherDsp))->assertForbidden();
        $this->actingAs($user)->get(route('scheduled-visits.show', $otherVisit))->assertForbidden();
        $this->actingAs($user)->get(route('attendance.show', $otherVisit))->assertForbidden();
        $this->actingAs($user)->post(route('attendance.corrections.store', $otherVisit), [
            'reason' => 'Out of scope',
        ])->assertForbidden();
        $this->actingAs($user)->get(route('supervisors.show', $other))->assertForbidden();
    }

    public function test_dsp_cannot_view_client_authorizations_via_policy(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $authorization = ClientAuthorization::factory()->forClient($client)->create();
        $user = $dsp->user()->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->denies('viewAny', ClientAuthorization::class));
        $this->assertTrue(Gate::forUser($user)->denies('view', $authorization));
        $this->assertTrue(Gate::forUser($user)->denies('create', ClientAuthorization::class));
    }

    public function test_dsp_scheduled_visit_detail_omits_workforce_dsp_picker(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ClientDspAssignment::factory()->forDsp($other)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/show')
                ->has('dsps', 0)
                ->where('visit.employee.id', $dsp->id));

        $this->actingAs($admin)
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dsps')
                ->where('dsps', fn ($dsps) => collect($dsps)->contains(
                    fn (array $row): bool => $row['id'] === $other->id,
                )));
    }

    public function test_deactivated_assignment_blocks_client_detail_but_keeps_own_historical_visit(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->inactive()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now()->subDay(),
        ]);
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('visits.show', $visit))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('attendance.show', $scheduled))
            ->assertOk();
    }

    public function test_dsp_cannot_open_another_dsps_scheduled_or_recorded_visit(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $otherScheduled = ScheduledVisit::factory()->forClient($client)->forDsp($other)->create();
        $otherVisit = Visit::factory()->forScheduledVisit($otherScheduled)->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)->get(route('scheduled-visits.show', $otherScheduled))->assertForbidden();
        $this->actingAs($user)->get(route('visits.show', $otherVisit))->assertForbidden();
        $this->actingAs($user)->get(route('attendance.show', $otherScheduled))->assertForbidden();
        $this->actingAs($user)->post(route('visits.clock-out', $otherVisit))->assertForbidden();
    }

    public function test_inactive_client_with_active_assignment_does_not_crash_dsp_detail(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->inactive()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/show')
                ->where('client.id', $client->id)
                ->has('authorizations', 0)
                ->has('assignments', 0)
                ->has('dspOptions', 0));
    }

    public function test_dsp_cannot_correct_attendance_and_cannot_act_on_out_of_scope_corrections(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $own = ScheduledVisit::factory()->forDsp($dsp)->create();
        $foreign = ScheduledVisit::factory()->forDsp($other)->create();
        $correction = AttendanceCorrection::factory()->create([
            'scheduled_visit_id' => $foreign->id,
        ]);
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->post(route('attendance.corrections.store', $own), [
                'reason' => 'DSP should not submit',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('attendance.corrections.approve', $correction), [
                'review_note' => 'No',
            ])
            ->assertForbidden();
    }

    public function test_guests_cannot_open_conversations_or_operational_routes(): void
    {
        $first = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $second = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $conversation = Conversation::factory()->between($first, $second)->create();

        $this->get(route('messages.index'))->assertRedirect(route('login'));
        $this->get(route('messages.show', $conversation))->assertRedirect(route('login'));
        $this->post(route('conversations.messages.store', $conversation), [
            'body' => 'Hello',
        ])->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_inactive_workforce_session_cannot_use_messages_or_clock_in(): void
    {
        $inactive = Employee::factory()->dsp()->inactive()->create();
        $peer = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $scheduled = ScheduledVisit::factory()->forDsp($inactive)->create();
        $user = $inactive->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('messages.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($user)
            ->post(route('conversations.store'), [
                'user_id' => $peer->id,
                'body' => 'Should not send',
            ])
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($user)
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'location_method' => 'gps_unavailable',
                'location_status' => 'denied',
                'unavailable_reason' => 'Browser GPS permission denied for local demo.',
            ])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_admin_weekly_availability_override_is_limited_to_dsp_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $payload = [
            'days' => [[
                'weekday' => 1,
                'is_available' => true,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]],
        ];

        $this->actingAs($admin)
            ->post(route('availability.override-weekly'), [
                'employee_id' => $dsp->id,
                ...$payload,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('availability.override-weekly'), [
                'employee_id' => $supervisor->id,
                ...$payload,
            ])
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('availability.override-weekly'), [
                'employee_id' => $dsp->id,
                ...$payload,
            ])
            ->assertForbidden();
    }

    public function test_care_overview_history_omits_other_dsp_notes_when_optional_visit_links_are_present(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        $ownScheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $ownVisit = Visit::factory()->forScheduledVisit($ownScheduled)->create();
        VisitTask::factory()->create([
            'visit_id' => $ownVisit->id,
            'title' => 'Own note',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Safe for this DSP.',
            'completed_at' => now()->subHour(),
        ]);

        $otherScheduled = ScheduledVisit::factory()->forClient($client)->forDsp($other)->create();
        $otherVisit = Visit::factory()->forScheduledVisit($otherScheduled)->create([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now()->subDay(),
        ]);
        VisitTask::factory()->create([
            'visit_id' => $otherVisit->id,
            'title' => 'Hidden note',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Must not leak.',
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $ownVisit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('care_history', 1)
                ->where('care_history.0.task_title', 'Own note'));
    }
}
