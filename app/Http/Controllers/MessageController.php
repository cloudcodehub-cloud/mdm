<?php

namespace App\Http\Controllers;

use App\Enums\AdminMessageReviewPurpose;
use App\Http\Requests\AdminReviewConversationRequest;
use App\Http\Requests\ManageGroupParticipantsRequest;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreGroupConversationRequest;
use App\Http\Requests\UpdateGroupConversationRequest;
use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', Conversation::class);

        $search = $request->string('search')->trim()->value();

        return Inertia::render('messages/index', $this->pageProps($user, null, [], $search));
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('view', $conversation);

        $adminReview = $this->messaging->adminHasActiveReviewAccess($user, $conversation)
            && ! $conversation->wasParticipant($user);

        $firstUnreadId = $adminReview ? null : $this->messaging->firstUnreadMessageId($user, $conversation);
        $messages = $this->messaging->messagesFor($conversation, $user);

        if (! $adminReview && $conversation->hasParticipant($user)) {
            $this->messaging->markRead($user, $conversation);
        }

        $search = $request->string('search')->trim()->value();
        $detail = $this->messaging->serializeConversation($conversation, $user, $adminReview);
        $detail['first_unread_id'] = $firstUnreadId;

        return Inertia::render('messages/index', $this->pageProps($user, $detail, $messages, $search));
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversation = $this->messaging->startConversation(
            $user,
            $request->recipient(),
            (string) ($request->validated('body') ?? ''),
            $request->careContext(),
            $request->file('attachment'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent.')]);

        if ($request->boolean('stay')) {
            return back();
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function storeGroup(StoreGroupConversationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversation = $this->messaging->startGroup(
            $user,
            (string) $request->validated('name'),
            $request->validated('participant_ids'),
            (string) ($request->validated('body') ?? ''),
            $request->file('attachment'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group created.')]);

        return redirect()->route('messages.show', $conversation);
    }

    public function preview(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 401);
        $this->authorize('create', Conversation::class);

        return response()->json($this->messaging->previewFor($actor, $user));
    }

    public function storeMessage(StoreConversationMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->messaging->sendMessage(
            $user,
            $conversation,
            (string) ($request->validated('body') ?? ''),
            $request->careContext(),
            $request->file('attachment'),
        );

        if ($request->boolean('stay')) {
            return back();
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function renameGroup(
        UpdateGroupConversationRequest $request,
        Conversation $conversation,
    ): RedirectResponse {
        $this->messaging->renameGroup(
            $request->user(),
            $conversation,
            (string) $request->validated('name'),
        );

        return back()->with('success', 'Group renamed.');
    }

    public function addParticipants(
        ManageGroupParticipantsRequest $request,
        Conversation $conversation,
    ): RedirectResponse {
        $this->messaging->addParticipants(
            $request->user(),
            $conversation,
            $request->validated('participant_ids'),
        );

        return back()->with('success', 'Participants updated.');
    }

    public function removeParticipant(
        Request $request,
        Conversation $conversation,
        User $user,
    ): RedirectResponse {
        $this->authorize('manage', $conversation);
        $this->messaging->removeParticipant($request->user(), $conversation, $user);

        return back()->with('success', 'Participant removed.');
    }

    public function leave(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('leave', $conversation);
        $this->messaging->leaveGroup($request->user(), $conversation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the group.')]);

        return redirect()->route('messages.index');
    }

    public function reviewIndex(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        $search = $request->string('search')->trim()->value();

        return Inertia::render('messages/admin-review', [
            'results' => $this->messaging->searchConversationsForAdminReview(
                $user,
                $search !== '' ? $search : null,
            ),
            'filters' => ['search' => $search],
            'purposes' => AdminMessageReviewPurpose::options(),
            'privacy_notice' => MessagingService::AGENCY_MESSAGING_NOTICE,
        ]);
    }

    public function review(
        AdminReviewConversationRequest $request,
        Conversation $conversation,
    ): RedirectResponse {
        $this->messaging->recordAdminReview(
            $request->user(),
            $conversation,
            AdminMessageReviewPurpose::from((string) $request->validated('purpose')),
            (string) $request->validated('reason'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Exceptional Admin review access granted for this session.'),
        ]);

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function pageProps(User $user, ?array $conversation, array $messages, string $search): array
    {
        return [
            'conversations' => $this->messaging->conversationSummaries($user),
            'conversation' => $conversation,
            'messages' => $messages,
            'recipients' => $this->messaging->searchableUsers($user, $search !== '' ? $search : null)
                ->map(fn (User $recipient): array => $this->messaging->serializeUser($recipient))
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
            ],
            'privacy_notice' => MessagingService::AGENCY_MESSAGING_NOTICE,
            'can' => [
                'announce' => $user->can('create', Announcement::class),
                'admin_review' => $user->isAdmin(),
                'create_group' => $user->can('create', Conversation::class),
            ],
        ];
    }
}
