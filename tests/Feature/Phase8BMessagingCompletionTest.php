<?php

namespace Tests\Feature;

use App\Enums\AdminMessageReviewPurpose;
use App\Enums\ConversationType;
use App\Enums\InAppNotificationType;
use App\Models\Conversation;
use App\Models\ConversationAdminReview;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\Employee;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase8BMessagingCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_private_group_membership_history_and_leave_boundaries(): void
    {
        $creator = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $member = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $outsider = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($creator)
            ->post(route('conversations.groups.store'), [
                'name' => 'Coverage huddle',
                'participant_ids' => [$member->id],
                'body' => 'Welcome to the group.',
            ])
            ->assertRedirect();

        $conversation = Conversation::query()
            ->where('type', ConversationType::Group)
            ->where('name', 'Coverage huddle')
            ->firstOrFail();

        $this->assertTrue($conversation->hasParticipant($creator));
        $this->assertTrue($conversation->hasParticipant($member));

        $this->actingAs($member)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Member reply while active.',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->post(route('conversations.leave', $conversation))
            ->assertRedirect(route('messages.index'));

        $this->assertFalse($conversation->fresh()->hasParticipant($member));
        $this->assertTrue($conversation->fresh()->wasParticipant($member));

        Carbon::setTestNow(now()->addMinute());

        $this->actingAs($creator)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'After member left.',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('messages/index')
                ->where('conversation.is_active_participant', false));

        $bodies = collect(app(MessagingService::class)->messagesFor($conversation->fresh(), $member))
            ->pluck('body');

        $this->assertTrue($bodies->contains('Member reply while active.'));
        $this->assertFalse($bodies->contains('After member left.'));

        $this->actingAs($outsider)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('conversations.messages.store', $conversation), [
                'body' => 'Should fail',
            ])
            ->assertForbidden();
    }

    public function test_message_attachments_require_conversation_authorization(): void
    {
        Storage::fake('local');

        $alice = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $bob = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $eve = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($alice)
            ->post(route('conversations.store'), [
                'user_id' => $bob->id,
                'body' => 'See attached screenshot.',
                'attachment' => UploadedFile::fake()->image('screen.png', 40, 40),
            ])
            ->assertRedirect();

        $attachment = ConversationMessageAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($bob)
            ->get(route('conversation-attachments.show', $attachment))
            ->assertOk();

        $this->actingAs($eve)
            ->get(route('conversation-attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_exceptional_admin_review_is_audited_and_supervisors_it_tech_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $itTech = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $itTech->forceFill(['it_tech_access' => true])->save();
        $alice = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $bob = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $conversation = Conversation::factory()->between($alice, $bob)->create();
        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $alice->id,
            'body' => 'Private planning note',
        ]);

        $this->actingAs($supervisor)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($itTech)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('messages.admin-review.store', $conversation), [
                'purpose' => AdminMessageReviewPurpose::Investigation->value,
                'reason' => 'Safety investigation into visit handoff concerns.',
            ])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseHas('conversation_admin_reviews', [
            'conversation_id' => $conversation->id,
            'admin_user_id' => $admin->id,
            'purpose' => AdminMessageReviewPurpose::Investigation->value,
        ]);

        $this->actingAs($admin)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.admin_review', true)
                ->where('messages.0.body', 'Private planning note')
                ->where('privacy_notice', fn ($notice) => str_contains($notice, 'not end-to-end encrypted')));
    }

    public function test_group_notifications_and_existing_direct_messages_still_work(): void
    {
        $alice = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $bob = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $cara = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($alice)
            ->post(route('conversations.store'), [
                'user_id' => $bob->id,
                'body' => 'Direct hello',
            ])
            ->assertRedirect();

        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $bob->id)
                ->where('type', InAppNotificationType::Message)
                ->exists()
        );

        $this->actingAs($alice)
            ->post(route('conversations.groups.store'), [
                'name' => 'Ops chat',
                'participant_ids' => [$bob->id, $cara->id],
                'body' => 'Group kickoff',
            ])
            ->assertRedirect();

        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $cara->id)
                ->where('type', InAppNotificationType::Message)
                ->where('title', 'like', 'Ops chat%')
                ->exists()
        );

        $this->actingAs($alice)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('messages/index')
                ->where('privacy_notice', MessagingService::AGENCY_MESSAGING_NOTICE)
                ->has('conversations', 2));
    }

    public function test_temporary_coverage_does_not_grant_private_message_access(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();
        $peer = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $conversation = Conversation::factory()->between($dsp, $peer)->create();
        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $dsp->id,
            'body' => 'Coverage unrelated private note',
        ]);

        $this->actingAs($supervisor)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->assertSame(0, ConversationAdminReview::query()->count());
    }
}
