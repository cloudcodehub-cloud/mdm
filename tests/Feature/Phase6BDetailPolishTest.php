<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\OrganizationSetting;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use App\Services\DashboardService;
use App\Support\DirectoryPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6BDetailPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dsp_countdown_payload_uses_organization_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 14:00:00', 'UTC'));
        OrganizationSetting::query()->update(['timezone' => 'America/Los_Angeles']);

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-21',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
        ]);

        $expected = DirectoryPresenter::visitStartsAtIso($scheduled);
        $this->assertSame('2026-09-21T14:00:00+00:00', $expected);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.clock_in_visit.starts_at_iso', $expected)
            );
    }

    public function test_active_visit_payload_keeps_persisted_clock_in_timestamp(): void
    {
        Carbon::setTestNow('2026-09-21 11:00:00');
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-21',
        ]);
        $clockedIn = Carbon::parse('2026-09-21 09:05:00', 'UTC');
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'clocked_in_at' => $clockedIn,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.clocked_in_at', $clockedIn->toIso8601String())
                ->where('can.record_tasks', true)
            );

        $this->assertSame(
            '2026-09-21 09:05:00',
            $visit->fresh()->clocked_in_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_profile_health_payload_is_role_scoped(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create([
            'date_of_birth' => null,
            'hired_on' => null,
            'address_line_1' => null,
        ]);
        Employee::factory()->dsp()->forSupervisor($other)->create([
            'first_name' => 'OutOfScope',
            'last_name' => 'Worker',
            'date_of_birth' => null,
            'hired_on' => null,
            'address_line_1' => null,
        ]);

        $items = app(DashboardService::class)->forUser($supervisor->user()->firstOrFail())['profiles_needing_attention'];
        $names = collect($items)->pluck('name');

        $this->assertTrue($names->contains($dsp->full_name));
        $this->assertFalse($names->contains(fn (string $name): bool => str_contains($name, 'OutOfScope')));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.profile_attention.employees', [])
                ->where('dashboard.profile_attention.clients', [])
                ->where('dashboard.profiles_needing_attention', [])
            );
    }

    public function test_contact_supervisor_exposes_only_authorized_context(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create();
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supervisor_contact.user_id', $supervisor->user()->firstOrFail()->id)
                ->where('supervisor_contact.name', $supervisor->full_name)
                ->where('supervisor_contact.available', true)
                ->missing('supervisor_contact.email')
                ->missing('supervisor_contact.ssn')
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('messages.preview', $supervisor->user()->firstOrFail()))
            ->assertOk()
            ->assertJsonPath('recipient.id', $supervisor->user()->firstOrFail()->id)
            ->assertJsonPath('recipient.name', $supervisor->user()->firstOrFail()->name)
            ->assertJsonMissingPath('recipient.email');
    }
}
