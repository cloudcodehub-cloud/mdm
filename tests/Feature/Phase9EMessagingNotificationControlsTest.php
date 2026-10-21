<?php

namespace Tests\Feature;

use App\Enums\InAppNotificationType;
use App\Models\Conversation;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9EMessagingNotificationControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{alice: User, bob: User, conversation: Conversation}
     */
    private function directThread(): array
    {
        $alice = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $bob = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $conversation = app(MessagingService::class)->startConversation(
            $alice,
            $bob,
            'Hello Bob',
        );

        return compact('alice', 'bob', 'conversation');
    }

    public function test_archive_is_participant_specific_and_restore_returns_to_inbox(): void
    {
        ['alice' => $alice, 'bob' => $bob, 'conversation' => $conversation] = $this->directThread();

        $this->actingAs($alice)
            ->post(route('conversations.archive', $conversation))
            ->assertRedirect(route('messages.index'));

        $this->actingAs($alice)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.mailbox', 'inbox')
                ->where('conversations', []));

        $this->actingAs($alice)
            ->get(route('messages.index', ['mailbox' => 'archived']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.mailbox', 'archived')
                ->where('conversations.0.id', $conversation->id)
                ->where('conversations.0.is_archived', true));

        // Bob's inbox is unaffected.
        $this->actingAs($bob)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.id', $conversation->id)
                ->where('conversations.0.is_archived', false));

        $this->actingAs($alice)
            ->post(route('conversations.restore', $conversation))
            ->assertRedirect(route('messages.show', $conversation));

        $this->actingAs($alice)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.id', $conversation->id)
                ->where('conversations.0.is_archived', false));

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Hello Bob',
        ]);
    }

    public function test_new_message_automatically_unarchives_for_recipients(): void
    {
        ['alice' => $alice, 'bob' => $bob, 'conversation' => $conversation] = $this->directThread();

        $this->actingAs($bob)
            ->post(route('conversations.archive', $conversation))
            ->assertRedirect();

        $this->assertTrue($conversation->fresh()->isArchivedFor($bob));

        $this->actingAs($alice)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Are you there?',
            ])
            ->assertRedirect();

        $this->assertFalse($conversation->fresh()->isArchivedFor($bob));

        $this->actingAs($bob)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.id', $conversation->id)
                ->where('conversations.0.is_archived', false));
    }

    public function test_mute_suppresses_message_notifications_but_not_operational_alerts(): void
    {
        ['alice' => $alice, 'bob' => $bob, 'conversation' => $conversation] = $this->directThread();

        InAppNotification::query()->where('user_id', $bob->id)->delete();

        $this->actingAs($bob)
            ->post(route('conversations.mute', $conversation))
            ->assertRedirect();

        $this->assertTrue($conversation->fresh()->isMutedFor($bob));

        $this->actingAs($alice)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Muted should not notify.',
            ])
            ->assertRedirect();

        $this->assertSame(
            0,
            InAppNotification::query()
                ->where('user_id', $bob->id)
                ->where('type', InAppNotificationType::Message)
                ->count(),
        );

        // Operational / required alerts are never suppressed by conversation mute.
        app(InAppNotificationService::class)->notify(
            $bob,
            InAppNotificationType::Incident,
            'Incident attention',
            'A submitted incident needs acknowledgment.',
            'incident:test:'.$bob->id,
            '/incidents',
        );
        app(InAppNotificationService::class)->notify(
            $bob,
            InAppNotificationType::Compliance,
            'Compliance alert',
            'Credential review required.',
            'compliance:test:'.$bob->id,
            '/employees',
        );
        app(InAppNotificationService::class)->notify(
            $bob,
            InAppNotificationType::StaffingUrgency,
            'Staffing urgency',
            'Unstaffed visit approaching.',
            'staffing:test:'.$bob->id,
            '/operations',
        );

        $this->assertSame(3, InAppNotification::query()->where('user_id', $bob->id)->count());

        $this->actingAs($bob)
            ->post(route('conversations.unmute', $conversation))
            ->assertRedirect();

        $this->actingAs($alice)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Unmuted should notify.',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            InAppNotification::query()
                ->where('user_id', $bob->id)
                ->where('type', InAppNotificationType::Message)
                ->count(),
        );
    }

    public function test_notification_hide_is_user_specific_and_preserves_records(): void
    {
        $alice = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $bob = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $aliceNote = InAppNotification::factory()->create([
            'user_id' => $alice->id,
            'type' => InAppNotificationType::Schedule,
            'title' => 'Alice schedule',
            'source_key' => 'schedule:alice:1',
        ]);
        $bobNote = InAppNotification::factory()->create([
            'user_id' => $bob->id,
            'type' => InAppNotificationType::Schedule,
            'title' => 'Bob schedule',
            'source_key' => 'schedule:bob:1',
        ]);

        $this->actingAs($alice)
            ->post(route('notifications.hide', $aliceNote))
            ->assertRedirect();

        $this->assertNotNull($aliceNote->fresh()->hidden_at);
        $this->assertNull($bobNote->fresh()->hidden_at);

        $this->assertSame(0, app(InAppNotificationService::class)->unreadCount($alice));
        $this->assertSame([], app(InAppNotificationService::class)->recentFor($alice));
        $this->assertSame(1, app(InAppNotificationService::class)->unreadCount($bob));

        $this->actingAs($bob)
            ->post(route('notifications.hide', $aliceNote))
            ->assertForbidden();

        $this->assertDatabaseHas('in_app_notifications', [
            'id' => $aliceNote->id,
            'title' => 'Alice schedule',
        ]);

        InAppNotification::factory()->create([
            'user_id' => $alice->id,
            'type' => InAppNotificationType::Announcement,
            'title' => 'Keep visible until clear',
            'source_key' => 'announcement:alice:2',
        ]);

        $this->actingAs($alice)
            ->post(route('notifications.hide-all'))
            ->assertRedirect();

        $this->assertSame(0, InAppNotification::query()
            ->where('user_id', $alice->id)
            ->visible()
            ->count());
        $this->assertSame(2, InAppNotification::query()
            ->where('user_id', $alice->id)
            ->count());
    }

    public function test_non_participant_cannot_archive_or_mute(): void
    {
        ['conversation' => $conversation] = $this->directThread();
        $outsider = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($outsider)
            ->post(route('conversations.archive', $conversation))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('conversations.mute', $conversation))
            ->assertForbidden();
    }
}
