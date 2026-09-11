<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Requests\StoreConversationRequest;
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

        return Inertia::render('messages/index', [
            'conversations' => $this->messaging->conversationSummaries($user),
            'conversation' => null,
            'messages' => [],
            'recipients' => $this->messaging->searchableUsers($user, $search !== '' ? $search : null)
                ->map(fn (User $recipient): array => $this->messaging->serializeUser($recipient))
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'announce' => $user->can('create', Announcement::class),
            ],
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('view', $conversation);

        $firstUnreadId = $this->messaging->firstUnreadMessageId($user, $conversation);
        $messages = $this->messaging->messagesFor($conversation, $user);
        $this->messaging->markRead($user, $conversation);

        $search = $request->string('search')->trim()->value();

        $other = $conversation->otherParticipant($user);

        return Inertia::render('messages/index', [
            'conversations' => $this->messaging->conversationSummaries($user),
            'conversation' => [
                'id' => $conversation->id,
                'other_user' => $other === null ? null : $this->messaging->serializeUser($other),
                'first_unread_id' => $firstUnreadId,
            ],
            'messages' => $messages,
            'recipients' => $this->messaging->searchableUsers($user, $search !== '' ? $search : null)
                ->map(fn (User $recipient): array => $this->messaging->serializeUser($recipient))
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'announce' => $user->can('create', Announcement::class),
            ],
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversation = $this->messaging->startConversation(
            $user,
            $request->recipient(),
            $request->validated('body'),
            $request->careContext(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent.')]);

        if ($request->boolean('stay')) {
            return back();
        }

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
            $request->validated('body'),
            $request->careContext(),
        );

        if ($request->boolean('stay')) {
            return back();
        }

        return redirect()->route('messages.show', $conversation);
    }
}
