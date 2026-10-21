<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10DMobileMessagingSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_messaging_page_supports_list_to_thread_mobile_handoff_markers(): void
    {
        $source = file_get_contents(resource_path('js/pages/messages/index.tsx'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('data-message-thread', $source);
        $this->assertStringContainsString('data-message-composer', $source);
        $this->assertStringContainsString('Back to conversations', $source);
        $this->assertStringContainsString(
            'max-md:bottom-[calc(4rem+env(safe-area-inset-bottom,0px))]',
            $source,
        );
        $this->assertStringContainsString('threadOpen && \'hidden lg:block\'', $source);
        $this->assertStringContainsString('!threadOpen && \'hidden lg:flex\'', $source);
    }

    public function test_calendar_provides_phone_agenda_and_preserves_desktop_grids(): void
    {
        $source = file_get_contents(resource_path('js/pages/scheduled-visits/calendar.tsx'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('data-schedule-phone-agenda', $source);
        $this->assertStringContainsString('function PhoneAgenda', $source);
        $this->assertStringContainsString('OpsRecordCard', $source);
        $this->assertStringContainsString('className="md:hidden"', $source);
        $this->assertStringContainsString('hidden md:block', $source);
        $this->assertStringContainsString('WeekGrid', $source);
        $this->assertStringContainsString('MonthGrid', $source);
        $this->assertStringContainsString('DayTimetable', $source);
    }

    public function test_authorized_roles_can_open_messages_and_calendar(): void
    {
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $admin = User::factory()->admin()->create();

        foreach ([$dsp, $supervisor, $admin] as $user) {
            $this->actingAs($user)
                ->get(route('messages.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('messages/index')
                    ->has('conversations')
                    ->has('mobileNav.shortcuts', 3));

            $this->actingAs($user)
                ->get('/scheduled-visits/calendar')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('scheduled-visits/calendar')
                    ->has('board.rows')
                    ->has('board.view')
                    ->has('sidebarOpen'));
        }
    }

    public function test_announcements_remain_reachable_from_messaging_surface(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('announcements/index')
                ->has('announcements')
                ->has('mobileNav.home'));
    }

    public function test_desktop_shell_markers_preserved_on_messages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sidebarOpen')
                ->has('mobileNav.home')
                ->has('mobileNav.more')
                ->has('mobileNav.available'));
    }
}
