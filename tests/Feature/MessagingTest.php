<?php

namespace Tests\Feature;

use App\Enums\AnnouncementAudience;
use App\Enums\EmploymentStatus;
use App\Enums\InAppNotificationType;
use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_supervisor_and_dsp_can_open_messages(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['admin@mdm.test', 'jordan.hale@mdm.test', 'maya.chen@mdm.test'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->get(route('messages.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('messages/index'));
        }
    }

    public function test_active_users_can_start_send_and_reply(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($dsp)
            ->post(route('conversations.store'), [
                'user_id' => $supervisor->id,
                'body' => 'Running late to the morning visit.',
            ])
            ->assertRedirect();

        $conversation = Conversation::query()
            ->where('participant_key', Conversation::participantKeyFor($dsp, $supervisor))
            ->firstOrFail();

        $this->assertTrue($conversation->hasParticipant($dsp));
        $this->assertTrue($conversation->hasParticipant($supervisor));
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $dsp->id,
            'body' => 'Running late to the morning visit.',
        ]);

        $this->actingAs($supervisor)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Thanks — I will cover the overlap.',
            ])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertSame(2, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_users_cannot_open_conversations_they_are_not_in(): void
    {
        $this->seed(DemoSeeder::class);
        $maya = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $jordan = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();
        $priya = User::query()->where('email', 'priya.nair@mdm.test')->firstOrFail();

        $conversation = Conversation::factory()->between($maya, $jordan)->create();

        $this->actingAs($priya)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($priya)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Should not send.',
            ])
            ->assertForbidden();
    }

    public function test_inactive_users_cannot_be_messaged_and_are_not_listed(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = Employee::factory()->dsp()->inactive()->create();
        $inactive->refresh();
        $inactiveUser = $inactive->user;
        $this->assertNotNull($inactiveUser);
        $this->assertSame(EmploymentStatus::Inactive, $inactive->employment_status);

        $this->actingAs($admin)
            ->post(route('conversations.store'), [
                'user_id' => $inactiveUser->id,
                'body' => 'Hello',
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipients', fn ($recipients) => collect($recipients)->every(
                    fn (array $recipient): bool => $recipient['id'] !== $inactiveUser->id,
                ))
            );
    }

    public function test_unread_counts_clear_when_the_conversation_is_opened(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($dsp)->post(route('conversations.store'), [
            'user_id' => $supervisor->id,
            'body' => 'Need a schedule check.',
        ]);

        $conversation = Conversation::query()
            ->where('participant_key', Conversation::participantKeyFor($dsp, $supervisor))
            ->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.unread_count', 1)
                ->where('inbox.unread_messages', 1)
            );

        $this->actingAs($supervisor)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.unread_count', 0)
                ->where('inbox.unread_messages', 0)
                ->where('messages.0.is_mine', false)
                ->where('conversation.first_unread_id', $conversation->messages()->value('id'))
            );
    }

    public function test_sending_a_message_creates_an_unread_notification_that_can_be_marked_read(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($dsp)->post(route('conversations.store'), [
            'user_id' => $supervisor->id,
            'body' => 'Clock-in question.',
        ]);

        $notification = InAppNotification::query()
            ->where('user_id', $supervisor->id)
            ->where('type', InAppNotificationType::Message)
            ->firstOrFail();

        $this->assertNull($notification->read_at);

        $this->actingAs($supervisor)
            ->getJson(route('inbox.activity', ['after' => now()->subMinute()->toIso8601String()]))
            ->assertOk()
            ->assertJsonPath('unread_notifications', 1)
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.read_at', null)
            ->assertJsonPath('toasts.0.source_key', $notification->source_key);

        $this->actingAs($supervisor)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_admin_can_publish_an_organization_announcement(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('announcements.store'), [
                'title' => 'Holiday coverage',
                'body' => 'Please confirm weekend availability.',
                'audience' => AnnouncementAudience::Everyone->value,
                'is_active' => true,
            ])
            ->assertRedirect(route('announcements.index'));

        $announcement = Announcement::query()->where('title', 'Holiday coverage')->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.announcements.0.title', 'Holiday coverage')
            );

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $dsp->id,
            'type' => InAppNotificationType::Announcement->value,
            'source_key' => 'announcement:'.$announcement->id,
        ]);
    }

    public function test_supervisor_announcements_are_limited_to_caseload_dsps(): void
    {
        $this->seed(DemoSeeder::class);
        $jordan = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();
        $maya = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();
        $nina = User::query()->where('email', 'nina.brooks@mdm.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($jordan)
            ->post(route('announcements.store'), [
                'title' => 'North caseload note',
                'body' => 'Bring updated MAR sheets.',
                'audience' => AnnouncementAudience::Dsps->value,
            ])
            ->assertRedirect(route('announcements.index'));

        $this->actingAs($jordan)
            ->post(route('announcements.store'), [
                'title' => 'Everyone note',
                'body' => 'Should be rejected.',
                'audience' => AnnouncementAudience::Everyone->value,
            ])
            ->assertSessionHasErrors('audience');

        $this->actingAs($maya)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('announcements.0.title', 'North caseload note')
            );

        $this->actingAs($nina)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'announcements',
                fn ($announcements): bool => collect($announcements)->every(
                    fn (array $announcement): bool => $announcement['title'] !== 'North caseload note',
                ),
            ));

        $this->actingAs($admin)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'announcements',
                fn ($announcements): bool => collect($announcements)->every(
                    fn (array $announcement): bool => $announcement['title'] !== 'North caseload note',
                ),
            ));
    }

    public function test_dsp_cannot_publish_announcements(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($dsp)
            ->post(route('announcements.store'), [
                'title' => 'DSP blast',
                'body' => 'Not allowed.',
                'audience' => AnnouncementAudience::Everyone->value,
            ])
            ->assertForbidden();

        $this->actingAs($dsp)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.create', false));
    }
}
