<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ScheduleCalendarService;
use App\Services\SchedulingMatchService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleCalendarWeekRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_week_header_range_matches_column_days_and_first_day_setting(): void
    {
        $admin = User::factory()->admin()->create();
        app(SettingsService::class)->updateOrganization(['first_day_of_week' => 1]);

        $this->actingAs($admin)
            ->get(route('scheduled-visits.calendar', [
                'view' => 'week',
                'date' => '2026-09-15',
                'group' => 'dsp',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/calendar')
                ->where('board.start', '2026-09-14')
                ->where('board.end', '2026-09-20')
                ->where('board.days', [
                    '2026-09-14',
                    '2026-09-15',
                    '2026-09-16',
                    '2026-09-17',
                    '2026-09-18',
                    '2026-09-19',
                    '2026-09-20',
                ])
                ->where('board.first_day_of_week', 1)
                ->where('board.prev_date', '2026-09-07')
                ->where('board.next_date', '2026-09-21')
                ->where('board.weekday_labels.0', 'Mon')
            );
    }

    public function test_week_range_uses_sunday_when_configured(): void
    {
        $admin = User::factory()->admin()->create();
        app(SettingsService::class)->updateOrganization(['first_day_of_week' => 0]);
        $board = app(ScheduleCalendarService::class)->view($admin, 'week', 'dsp', '2026-09-15', []);

        $this->assertSame('2026-09-13', $board['start']);
        $this->assertSame('2026-09-19', $board['end']);
        $this->assertSame($board['days'][0], $board['start']);
        $this->assertSame($board['days'][6], $board['end']);
        $this->assertCount(7, $board['days']);
    }

    public function test_authorization_warnings_name_each_selected_service(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        ClientAuthorization::factory()->forClient($client)->create([
            'service_type' => 'Personal Care',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $warning = app(SchedulingMatchService::class)->authorizationWarning(
            $client,
            'Personal Care · Community Integration',
            '2026-09-14',
        );

        $this->assertNotNull($warning);
        $this->assertCount(1, $warning['items']);
        $this->assertSame('Community Integration', $warning['items'][0]['service']);
        $this->assertStringContainsString('Community Integration — no active authorization for Sep 14.', $warning['message']);
        $this->assertStringNotContainsString('Personal Care —', $warning['message']);
    }

    public function test_week_columns_include_visits_on_boundary_days(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        app(SettingsService::class)->updateOrganization(['first_day_of_week' => 1]);

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-14',
            'supervisor_id' => $supervisor->id,
        ]);
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-20',
            'supervisor_id' => $supervisor->id,
        ]);

        $board = app(ScheduleCalendarService::class)->view($admin, 'week', 'dsp', '2026-09-16', []);

        $this->assertSame(['2026-09-14', '2026-09-20'], array_values(array_unique(array_map(
            fn (array $visit): string => $visit['service_date'],
            $board['rows'][0]['visits'],
        ))));
    }
}
