<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketWorkImpact;
use App\Http\Requests\MarkDuplicateSupportTicketRequest;
use App\Http\Requests\ReassignSupportTicketRequest;
use App\Http\Requests\ReplySupportTicketRequest;
use App\Http\Requests\ResolveSupportTicketRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request, SupportTicketService $tickets): Response
    {
        $this->authorize('viewAny', SupportTicket::class);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $tab = $request->string('tab')->toString() ?: null;
        $page = $tickets->listQueryFor(
            $user,
            $tab,
            $request->string('search')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('category')->toString() ?: null,
            $request->string('urgency')->toString() ?: null,
        )->paginate(25)->withQueryString();

        return Inertia::render('support-tickets/index', [
            'tickets' => [
                'data' => $page->getCollection()
                    ->map(fn (SupportTicket $ticket): array => $tickets->serializeListItem($ticket, $user))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'total' => $page->total(),
                ],
                'links' => [
                    'prev' => $page->previousPageUrl(),
                    'next' => $page->nextPageUrl(),
                ],
            ],
            'filters' => [
                'tab' => $tab,
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'category' => $request->string('category')->toString() ?: null,
                'urgency' => $request->string('urgency')->toString() ?: null,
            ],
            'categories' => SupportTicketCategory::options(),
            'metrics' => $user->can('viewMetrics', SupportTicket::class) ? $tickets->metrics() : null,
            'can' => [
                'create' => $user->can('create', SupportTicket::class),
                'it_tech' => $user->hasItTechAccess(),
                'admin' => $user->isAdmin(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SupportTicket::class);

        return Inertia::render('support-tickets/create', [
            'categories' => SupportTicketCategory::options(),
            'work_impacts' => SupportTicketWorkImpact::options(),
        ]);
    }

    public function store(StoreSupportTicketRequest $request, SupportTicketService $tickets): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        $ticket = $tickets->create($request->user(), $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Support ticket :number submitted.', ['number' => $ticket->ticket_number]),
        ]);

        return redirect()->route('support-tickets.show', $ticket);
    }

    public function show(Request $request, SupportTicket $support_ticket, SupportTicketService $tickets): Response
    {
        $this->authorize('view', $support_ticket);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $tickets->closeExpiredResolved();
        $support_ticket->refresh();

        return Inertia::render('support-tickets/show', [
            'ticket' => $tickets->serializeDetail($support_ticket, $user),
            'it_tech_options' => $user->hasItTechAccess() ? $tickets->itTechOptions() : [],
            'can' => [
                'claim' => $user->can('claim', $support_ticket),
                'reply' => $user->can('reply', $support_ticket),
                'resolve' => $user->can('resolve', $support_ticket),
                'reopen' => $user->can('reopen', $support_ticket),
                'cancel' => $user->can('cancel', $support_ticket),
                'reassign' => $user->can('reassign', $support_ticket),
                'takeover' => $user->can('takeover', $support_ticket),
                'mark_duplicate' => $user->can('markDuplicate', $support_ticket),
                'view_full' => $user->can('viewFull', $support_ticket),
            ],
        ]);
    }

    public function claim(Request $request, SupportTicket $support_ticket, SupportTicketService $tickets): RedirectResponse
    {
        $this->authorize('claim', $support_ticket);
        $tickets->claim($request->user(), $support_ticket);

        return back()->with('success', 'Ticket claimed.');
    }

    public function reply(
        ReplySupportTicketRequest $request,
        SupportTicket $support_ticket,
        SupportTicketService $tickets,
    ): RedirectResponse {
        $data = $request->validated();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        $tickets->reply($request->user(), $support_ticket, $data);

        return back()->with('success', 'Reply posted.');
    }

    public function resolve(
        ResolveSupportTicketRequest $request,
        SupportTicket $support_ticket,
        SupportTicketService $tickets,
    ): RedirectResponse {
        $tickets->resolve(
            $request->user(),
            $support_ticket,
            (string) $request->validated('resolution_note'),
        );

        return back()->with('success', 'Ticket marked Resolved.');
    }

    public function reopen(Request $request, SupportTicket $support_ticket, SupportTicketService $tickets): RedirectResponse
    {
        $this->authorize('reopen', $support_ticket);
        $tickets->reopen($request->user(), $support_ticket);

        return back()->with('success', 'Ticket reopened.');
    }

    public function cancel(Request $request, SupportTicket $support_ticket, SupportTicketService $tickets): RedirectResponse
    {
        $this->authorize('cancel', $support_ticket);
        $tickets->cancel($request->user(), $support_ticket);

        return back()->with('success', 'Ticket withdrawn.');
    }

    public function reassign(
        ReassignSupportTicketRequest $request,
        SupportTicket $support_ticket,
        SupportTicketService $tickets,
    ): RedirectResponse {
        $tickets->reassign($request->user(), $support_ticket, $request->technician());

        return back()->with('success', 'Ticket reassigned.');
    }

    public function takeover(Request $request, SupportTicket $support_ticket, SupportTicketService $tickets): RedirectResponse
    {
        $this->authorize('takeover', $support_ticket);
        $tickets->takeover($request->user(), $support_ticket);

        return back()->with('success', 'Ticket taken over.');
    }

    public function markDuplicate(
        MarkDuplicateSupportTicketRequest $request,
        SupportTicket $support_ticket,
        SupportTicketService $tickets,
    ): RedirectResponse {
        $tickets->markDuplicate($request->user(), $support_ticket, $request->canonicalTicket());

        return back()->with('success', 'Ticket marked as duplicate.');
    }
}
