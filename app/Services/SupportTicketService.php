<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketHistoryAction;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketWorkImpact;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketHistoryEvent;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * @param  array{
     *     category: string,
     *     subject: string,
     *     description: string,
     *     work_impact: string,
     *     attachment?: UploadedFile|null
     * }  $data
     */
    public function create(User $requester, array $data): SupportTicket
    {
        $category = SupportTicketCategory::from($data['category']);
        $workImpact = SupportTicketWorkImpact::from($data['work_impact']);

        return DB::transaction(function () use ($requester, $data, $category, $workImpact): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'ticket_number' => $this->nextTicketNumber(),
                'requester_user_id' => $requester->id,
                'assigned_user_id' => null,
                'status' => SupportTicketStatus::Unassigned,
                'category' => $category,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'work_impact' => $workImpact,
                'urgency' => $workImpact->urgency(),
            ]);

            $this->recordHistory($ticket, $requester, SupportTicketHistoryAction::Created, [
                'category' => $category->value,
                'subject' => $ticket->subject,
                'work_impact' => $workImpact->value,
                'urgency' => $ticket->urgency->value,
            ]);

            if (($data['attachment'] ?? null) instanceof UploadedFile) {
                $this->storeAttachment($ticket, $requester, $data['attachment'], null, true);
            }

            $this->notifyItTechQueue($ticket, 'new', "New support ticket {$ticket->ticket_number}");
            $this->notifyAdmins($ticket, 'new', "New support ticket {$ticket->ticket_number}");

            return $ticket->fresh(['requester', 'assignee', 'attachments', 'replies', 'historyEvents']) ?? $ticket;
        });
    }

    public function claim(User $technician, SupportTicket $ticket): SupportTicket
    {
        $this->assertItTech($technician);
        $this->assertStatus($ticket, [SupportTicketStatus::Unassigned]);

        return DB::transaction(function () use ($technician, $ticket): SupportTicket {
            $ticket->forceFill([
                'assigned_user_id' => $technician->id,
                'status' => SupportTicketStatus::InProgress,
            ])->save();

            $this->recordHistory($ticket, $technician, SupportTicketHistoryAction::Claimed);

            $this->notifyUser(
                $ticket->requester,
                $ticket,
                'claimed',
                "Ticket {$ticket->ticket_number} claimed",
                'An IT/Tech technician has claimed your support ticket.',
            );

            return $ticket->refresh();
        });
    }

    public function reassign(User $actor, SupportTicket $ticket, User $toTechnician): SupportTicket
    {
        $this->assertItTech($actor);
        $this->assertItTech($toTechnician);
        $this->assertAssignedTo($actor, $ticket);
        $this->assertStatus($ticket, [SupportTicketStatus::InProgress, SupportTicketStatus::Resolved]);

        if ($toTechnician->id === $ticket->assigned_user_id) {
            return $ticket;
        }

        return DB::transaction(function () use ($actor, $ticket, $toTechnician): SupportTicket {
            $fromId = $ticket->assigned_user_id;

            $ticket->forceFill([
                'assigned_user_id' => $toTechnician->id,
                'status' => SupportTicketStatus::InProgress,
                'resolution_note' => null,
                'resolved_at' => null,
            ])->save();

            $this->recordHistory($ticket, $actor, SupportTicketHistoryAction::Reassigned, [
                'from_user_id' => $fromId,
                'to_user_id' => $toTechnician->id,
            ]);

            $this->notifyUser(
                $toTechnician,
                $ticket,
                'reassigned',
                "Ticket {$ticket->ticket_number} assigned to you",
                'A support ticket was reassigned to you.',
            );
            $this->notifyUser(
                $ticket->requester,
                $ticket,
                'reassigned-requester',
                "Ticket {$ticket->ticket_number} reassigned",
                'Your support ticket was reassigned to another technician.',
            );

            return $ticket->refresh();
        });
    }

    public function takeover(User $technician, SupportTicket $ticket): SupportTicket
    {
        $this->assertItTech($technician);
        $this->assertStatus($ticket, [SupportTicketStatus::InProgress, SupportTicketStatus::Resolved]);

        if ($ticket->assigned_user_id === $technician->id) {
            return $ticket;
        }

        return DB::transaction(function () use ($technician, $ticket): SupportTicket {
            $fromId = $ticket->assigned_user_id;

            $ticket->forceFill([
                'assigned_user_id' => $technician->id,
                'status' => SupportTicketStatus::InProgress,
                'resolution_note' => null,
                'resolved_at' => null,
            ])->save();

            $this->recordHistory($ticket, $technician, SupportTicketHistoryAction::Takeover, [
                'from_user_id' => $fromId,
            ]);

            if ($fromId !== null) {
                $previous = User::query()->find($fromId);
                if ($previous !== null) {
                    $this->notifyUser(
                        $previous,
                        $ticket,
                        'takeover-previous',
                        "Ticket {$ticket->ticket_number} taken over",
                        'Another IT/Tech user took over this support ticket.',
                    );
                }
            }

            $this->notifyUser(
                $ticket->requester,
                $ticket,
                'takeover-requester',
                "Ticket {$ticket->ticket_number} technician changed",
                'A different IT/Tech technician is now working your ticket.',
            );

            return $ticket->refresh();
        });
    }

    /**
     * @param  array{body: string, attachment?: UploadedFile|null}  $data
     */
    public function reply(User $actor, SupportTicket $ticket, array $data): SupportTicketReply
    {
        $isRequester = $actor->id === $ticket->requester_user_id;
        $isAssignee = $ticket->assigned_user_id !== null && $actor->id === $ticket->assigned_user_id;

        if (! $isRequester && ! $isAssignee && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'body' => 'You are not allowed to reply to this ticket.',
            ]);
        }

        if ($actor->isAdmin() && ! $isRequester && ! $isAssignee) {
            throw ValidationException::withMessages([
                'body' => 'Admin oversight does not include posting as the ticket conversation party.',
            ]);
        }

        if ($ticket->status === SupportTicketStatus::Closed
            || $ticket->status === SupportTicketStatus::Cancelled
            || $ticket->status === SupportTicketStatus::Duplicate) {
            throw ValidationException::withMessages([
                'body' => 'This ticket is no longer open for replies.',
            ]);
        }

        if ($ticket->status === SupportTicketStatus::Unassigned && ! $isRequester) {
            throw ValidationException::withMessages([
                'body' => 'Claim the ticket before replying.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $data, $isRequester): SupportTicketReply {
            if ($isRequester && $ticket->status === SupportTicketStatus::Resolved) {
                if (! $ticket->isWithinReopenWindow()) {
                    throw ValidationException::withMessages([
                        'body' => 'The reopen window has ended. Submit a new support ticket if the issue continues.',
                    ]);
                }

                $ticket->forceFill([
                    'status' => SupportTicketStatus::InProgress,
                    'resolution_note' => null,
                    'resolved_at' => null,
                ])->save();

                $this->recordHistory($ticket, $actor, SupportTicketHistoryAction::Reopened, [
                    'via' => 'requester_reply',
                ]);

                if ($ticket->assigned_user_id !== null) {
                    $assignee = User::query()->find($ticket->assigned_user_id);
                    if ($assignee !== null) {
                        $this->notifyUser(
                            $assignee,
                            $ticket,
                            'reopened',
                            "Ticket {$ticket->ticket_number} reopened",
                            'The requester replied and reopened this ticket.',
                        );
                    }
                }
            }

            $reply = SupportTicketReply::query()->create([
                'support_ticket_id' => $ticket->id,
                'author_user_id' => $actor->id,
                'body' => $data['body'],
            ]);

            $this->recordHistory($ticket, $actor, SupportTicketHistoryAction::Replied, [
                'reply_id' => $reply->id,
            ]);

            if (($data['attachment'] ?? null) instanceof UploadedFile) {
                $this->storeAttachment($ticket, $actor, $data['attachment'], $reply->id, false);
            }

            $recipient = $isRequester
                ? ($ticket->assigned_user_id !== null ? User::query()->find($ticket->assigned_user_id) : null)
                : $ticket->requester;

            if ($recipient !== null) {
                $this->notifyUser(
                    $recipient,
                    $ticket,
                    'reply-'.$reply->id,
                    "New reply on {$ticket->ticket_number}",
                    'There is a new reply on a support ticket.',
                );
            }

            return $reply;
        });
    }

    public function resolve(User $technician, SupportTicket $ticket, string $resolutionNote): SupportTicket
    {
        $this->assertItTech($technician);
        $this->assertAssignedTo($technician, $ticket);
        $this->assertStatus($ticket, [SupportTicketStatus::InProgress]);

        if (trim($resolutionNote) === '') {
            throw ValidationException::withMessages([
                'resolution_note' => 'A Resolution Note is required before marking Resolved.',
            ]);
        }

        return DB::transaction(function () use ($technician, $ticket, $resolutionNote): SupportTicket {
            $ticket->forceFill([
                'status' => SupportTicketStatus::Resolved,
                'resolution_note' => $resolutionNote,
                'resolved_at' => now(),
            ])->save();

            $this->recordHistory($ticket, $technician, SupportTicketHistoryAction::Resolved, [
                'resolution_note' => $resolutionNote,
            ]);

            $this->notifyUser(
                $ticket->requester,
                $ticket,
                'resolved',
                "Ticket {$ticket->ticket_number} resolved",
                'Your support ticket was marked Resolved. You may reopen within 7 calendar days.',
            );

            return $ticket->refresh();
        });
    }

    public function reopen(User $requester, SupportTicket $ticket): SupportTicket
    {
        if ($requester->id !== $ticket->requester_user_id) {
            throw ValidationException::withMessages([
                'ticket' => 'Only the requester may reopen this ticket.',
            ]);
        }

        $this->assertStatus($ticket, [SupportTicketStatus::Resolved]);

        if (! $ticket->isWithinReopenWindow()) {
            throw ValidationException::withMessages([
                'ticket' => 'The reopen window has ended. Submit a new support ticket if the issue continues.',
            ]);
        }

        return DB::transaction(function () use ($requester, $ticket): SupportTicket {
            $ticket->forceFill([
                'status' => SupportTicketStatus::InProgress,
                'resolution_note' => null,
                'resolved_at' => null,
            ])->save();

            $this->recordHistory($ticket, $requester, SupportTicketHistoryAction::Reopened, [
                'via' => 'requester_action',
            ]);

            if ($ticket->assigned_user_id !== null) {
                $assignee = User::query()->find($ticket->assigned_user_id);
                if ($assignee !== null) {
                    $this->notifyUser(
                        $assignee,
                        $ticket,
                        'reopened-action',
                        "Ticket {$ticket->ticket_number} reopened",
                        'The requester reopened this support ticket.',
                    );
                }
            }

            return $ticket->refresh();
        });
    }

    public function cancel(User $requester, SupportTicket $ticket): SupportTicket
    {
        if ($requester->id !== $ticket->requester_user_id) {
            throw ValidationException::withMessages([
                'ticket' => 'Only the requester may withdraw this ticket.',
            ]);
        }

        $this->assertStatus($ticket, [SupportTicketStatus::Unassigned, SupportTicketStatus::InProgress]);

        return DB::transaction(function () use ($requester, $ticket): SupportTicket {
            $ticket->forceFill([
                'status' => SupportTicketStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->recordHistory($ticket, $requester, SupportTicketHistoryAction::Cancelled);

            if ($ticket->assigned_user_id !== null) {
                $assignee = User::query()->find($ticket->assigned_user_id);
                if ($assignee !== null) {
                    $this->notifyUser(
                        $assignee,
                        $ticket,
                        'cancelled',
                        "Ticket {$ticket->ticket_number} withdrawn",
                        'The requester withdrew this support ticket.',
                    );
                }
            }

            return $ticket->refresh();
        });
    }

    public function markDuplicate(User $technician, SupportTicket $ticket, SupportTicket $canonical): SupportTicket
    {
        $this->assertItTech($technician);
        $this->assertStatus($ticket, [
            SupportTicketStatus::Unassigned,
            SupportTicketStatus::InProgress,
            SupportTicketStatus::Resolved,
        ]);

        if ($ticket->status !== SupportTicketStatus::Unassigned
            && $ticket->assigned_user_id !== $technician->id) {
            throw ValidationException::withMessages([
                'ticket' => 'Claim or take over the ticket before marking it duplicate.',
            ]);
        }

        if ($canonical->id === $ticket->id) {
            throw ValidationException::withMessages([
                'duplicate_of_ticket_id' => 'Choose a different ticket as the canonical reference.',
            ]);
        }

        return DB::transaction(function () use ($technician, $ticket, $canonical): SupportTicket {
            if ($ticket->status === SupportTicketStatus::Unassigned) {
                $ticket->forceFill([
                    'assigned_user_id' => $technician->id,
                ])->save();
            }

            $ticket->forceFill([
                'status' => SupportTicketStatus::Duplicate,
                'duplicate_of_ticket_id' => $canonical->id,
                'closed_at' => now(),
            ])->save();

            $this->recordHistory($ticket, $technician, SupportTicketHistoryAction::MarkedDuplicate, [
                'duplicate_of_ticket_id' => $canonical->id,
                'duplicate_of_ticket_number' => $canonical->ticket_number,
            ]);

            $this->notifyUser(
                $ticket->requester,
                $ticket,
                'duplicate',
                "Ticket {$ticket->ticket_number} marked duplicate",
                "This ticket was marked as a duplicate of {$canonical->ticket_number}.",
            );

            return $ticket->refresh();
        });
    }

    public function closeExpiredResolved(?\DateTimeInterface $now = null): int
    {
        $now ??= now();
        $closed = 0;

        SupportTicket::query()
            ->where('status', SupportTicketStatus::Resolved)
            ->whereNotNull('resolved_at')
            ->orderBy('id')
            ->each(function (SupportTicket $ticket) use ($now, &$closed): void {
                if ($ticket->isWithinReopenWindow($now)) {
                    return;
                }

                $ticket->forceFill([
                    'status' => SupportTicketStatus::Closed,
                    'closed_at' => $now,
                ])->save();

                $this->recordHistory($ticket, null, SupportTicketHistoryAction::Closed, [
                    'reason' => 'reopen_window_elapsed',
                ]);
                $closed++;
            });

        return $closed;
    }

    /**
     * @return Builder<SupportTicket>
     */
    public function listQueryFor(User $user, ?string $tab = null, ?string $search = null, ?string $status = null, ?string $category = null, ?string $urgency = null): Builder
    {
        $this->closeExpiredResolved();

        $query = SupportTicket::query()
            ->with(['requester.employee', 'assignee'])
            ->orderByDesc('created_at');

        if ($user->isAdmin()) {
            // Admin oversight: all tickets
        } elseif ($user->hasItTechAccess()) {
            if ($tab === 'unassigned') {
                $query->where('status', SupportTicketStatus::Unassigned);
            } elseif ($tab === 'mine') {
                $query->where('assigned_user_id', $user->id)
                    ->whereIn('status', [SupportTicketStatus::InProgress, SupportTicketStatus::Resolved]);
            } elseif ($tab === 'resolved') {
                $query->where('status', SupportTicketStatus::Resolved)
                    ->where(function (Builder $builder) use ($user): void {
                        $builder->where('assigned_user_id', $user->id)
                            ->orWhereNull('assigned_user_id');
                    });
            } elseif ($tab === 'closed') {
                $query->whereIn('status', [
                    SupportTicketStatus::Closed,
                    SupportTicketStatus::Cancelled,
                    SupportTicketStatus::Duplicate,
                ])->where(function (Builder $builder) use ($user): void {
                    $builder->where('assigned_user_id', $user->id)
                        ->orWhere('requester_user_id', $user->id);
                });
            } else {
                $query->where(function (Builder $builder) use ($user): void {
                    $builder->where('status', SupportTicketStatus::Unassigned)
                        ->orWhere('assigned_user_id', $user->id)
                        ->orWhere('requester_user_id', $user->id);
                });
            }
        } else {
            $query->where('requester_user_id', $user->id);

            if ($tab === 'open') {
                $query->whereIn('status', [
                    SupportTicketStatus::Unassigned,
                    SupportTicketStatus::InProgress,
                    SupportTicketStatus::Resolved,
                ]);
            } elseif ($tab === 'history') {
                $query->whereIn('status', [
                    SupportTicketStatus::Closed,
                    SupportTicketStatus::Cancelled,
                    SupportTicketStatus::Duplicate,
                ]);
            }
        }

        if (filled($status)) {
            $query->where('status', $status);
        }

        if (filled($category)) {
            $query->where('category', $category);
        }

        if (filled($urgency)) {
            $query->where('urgency', $urgency);
        }

        if (filled($search)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $builder) use ($term, $user): void {
                $builder->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term);

                if ($user->isAdmin() || $user->hasItTechAccess()) {
                    $builder->orWhereHas('requester', function (Builder $requester) use ($term): void {
                        $requester->where('name', 'like', $term);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeListItem(SupportTicket $ticket, User $viewer): array
    {
        $summaryOnly = $this->isQueueSummaryOnly($ticket, $viewer);

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'category' => $ticket->category->value,
            'category_label' => $ticket->category->label(),
            'subject' => $ticket->subject,
            'urgency' => $ticket->urgency->value,
            'urgency_label' => $ticket->urgency->label(),
            'work_impact' => $ticket->work_impact->value,
            'work_impact_label' => $ticket->work_impact->label(),
            'requester_name' => $ticket->requester?->name,
            'assignee_name' => $ticket->assignee?->name,
            'age_label' => $ticket->created_at?->diffForHumans(syntax: true),
            'created_at_label' => $ticket->created_at !== null
                ? $this->settings->formatDateTime($ticket->created_at)
                : null,
            'summary_only' => $summaryOnly,
            'description' => $summaryOnly ? null : $ticket->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDetail(SupportTicket $ticket, User $viewer): array
    {
        $summaryOnly = $this->isQueueSummaryOnly($ticket, $viewer);
        $ticket->loadMissing([
            'requester.employee',
            'assignee',
            'replies.author',
            'attachments.uploader',
            'historyEvents.actor',
            'duplicateOf',
        ]);

        $base = $this->serializeListItem($ticket, $viewer);

        if ($summaryOnly) {
            return [
                ...$base,
                'description' => null,
                'resolution_note' => null,
                'replies' => [],
                'attachments' => [],
                'history' => [],
                'requester' => [
                    'name' => $ticket->requester?->name,
                ],
                'can_view_full' => false,
            ];
        }

        return [
            ...$base,
            'description' => $ticket->description,
            'resolution_note' => $ticket->resolution_note,
            'resolved_at_label' => $ticket->resolved_at !== null
                ? $this->settings->formatDateTime($ticket->resolved_at)
                : null,
            'reopen_deadline_label' => $ticket->reopenDeadline() !== null
                ? $this->settings->formatDateTime($ticket->reopenDeadline())
                : null,
            'within_reopen_window' => $ticket->isWithinReopenWindow(),
            'duplicate_of' => $ticket->duplicateOf !== null
                ? [
                    'id' => $ticket->duplicateOf->id,
                    'ticket_number' => $ticket->duplicateOf->ticket_number,
                ]
                : null,
            'requester' => $this->serializeRequesterForItTech($ticket),
            'can_view_full' => true,
            'replies' => $ticket->replies->map(fn (SupportTicketReply $reply): array => [
                'id' => $reply->id,
                'body' => $reply->body,
                'author_name' => $reply->author?->name,
                'author_user_id' => $reply->author_user_id,
                'created_at_label' => $reply->created_at !== null
                    ? $this->settings->formatDateTime($reply->created_at)
                    : null,
            ])->values()->all(),
            'attachments' => $ticket->attachments->map(fn (SupportTicketAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'is_initial' => $attachment->is_initial,
                'uploaded_by' => $attachment->uploader?->name,
                'created_at_label' => $attachment->created_at !== null
                    ? $this->settings->formatDateTime($attachment->created_at)
                    : null,
            ])->values()->all(),
            'history' => $ticket->historyEvents->map(fn (SupportTicketHistoryEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action->value,
                'actor_name' => $event->actor?->name ?? 'System',
                'payload' => $event->payload,
                'created_at_label' => $event->created_at !== null
                    ? $this->settings->formatDateTime($event->created_at)
                    : null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{total: int, open: int, resolved_closed: int, average_resolution_hours: float|null, by_category: list<array{category: string, label: string, count: int}>}
     */
    public function metrics(): array
    {
        $this->closeExpiredResolved();

        $total = SupportTicket::query()->count();
        $open = SupportTicket::query()->whereIn('status', [
            SupportTicketStatus::Unassigned,
            SupportTicketStatus::InProgress,
            SupportTicketStatus::Resolved,
        ])->count();
        $resolvedClosed = SupportTicket::query()->whereIn('status', [
            SupportTicketStatus::Resolved,
            SupportTicketStatus::Closed,
        ])->count();

        $resolved = SupportTicket::query()->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $avgSeconds = $resolved->isNotEmpty()
            ? $resolved->avg(fn (SupportTicket $ticket): float => (float) $ticket->created_at?->diffInSeconds($ticket->resolved_at))
            : null;

        $byCategory = [];
        foreach (SupportTicketCategory::cases() as $category) {
            $byCategory[] = [
                'category' => $category->value,
                'label' => $category->label(),
                'count' => SupportTicket::query()->where('category', $category)->count(),
            ];
        }

        return [
            'total' => $total,
            'open' => $open,
            'resolved_closed' => $resolvedClosed,
            'average_resolution_hours' => $avgSeconds !== null ? round(((float) $avgSeconds) / 3600, 1) : null,
            'by_category' => $byCategory,
        ];
    }

    public function isQueueSummaryOnly(SupportTicket $ticket, User $viewer): bool
    {
        if ($viewer->isAdmin() || $viewer->id === $ticket->requester_user_id) {
            return false;
        }

        if ($viewer->hasItTechAccess()
            && $ticket->status === SupportTicketStatus::Unassigned
            && $ticket->assigned_user_id === null) {
            return true;
        }

        if ($viewer->hasItTechAccess() && $ticket->assigned_user_id === $viewer->id) {
            return false;
        }

        return ! $viewer->hasItTechAccess();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function itTechOptions(): array
    {
        return User::query()
            ->where('it_tech_access', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    private function nextTicketNumber(): string
    {
        $latest = SupportTicket::query()
            ->orderByDesc('id')
            ->value('ticket_number');

        $next = 1;
        if (is_string($latest) && preg_match('/^IT-(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('IT-%06d', $next);
    }

    private function storeAttachment(
        SupportTicket $ticket,
        User $uploader,
        UploadedFile $file,
        ?int $replyId,
        bool $isInitial,
    ): SupportTicketAttachment {
        $path = $file->store('support-tickets/'.$ticket->id, 'local');

        $attachment = SupportTicketAttachment::query()->create([
            'support_ticket_id' => $ticket->id,
            'support_ticket_reply_id' => $replyId,
            'uploaded_by_user_id' => $uploader->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'is_initial' => $isInitial,
        ]);

        $this->recordHistory($ticket, $uploader, SupportTicketHistoryAction::AttachmentAdded, [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ]);

        return $attachment;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function recordHistory(
        SupportTicket $ticket,
        ?User $actor,
        SupportTicketHistoryAction $action,
        ?array $payload = null,
    ): void {
        SupportTicketHistoryEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'payload' => $payload,
        ]);
    }

    private function notifyUser(User $recipient, SupportTicket $ticket, string $kind, string $title, string $body): void
    {
        $this->notifications->notify(
            $recipient,
            InAppNotificationType::SupportTicket,
            $title,
            $body,
            'support-ticket:'.$ticket->id.':'.$kind,
            route('support-tickets.show', $ticket),
        );
    }

    private function notifyItTechQueue(SupportTicket $ticket, string $kind, string $title): void
    {
        User::query()
            ->where('it_tech_access', true)
            ->where('id', '!=', $ticket->requester_user_id)
            ->orderBy('id')
            ->each(function (User $tech) use ($ticket, $kind, $title): void {
                $this->notifyUser(
                    $tech,
                    $ticket,
                    $kind.'-queue-'.$tech->id,
                    $title,
                    'A new ticket is waiting in the Unassigned queue.',
                );
            });
    }

    private function notifyAdmins(SupportTicket $ticket, string $kind, string $title): void
    {
        User::query()
            ->where('role', Role::Admin)
            ->where('id', '!=', $ticket->requester_user_id)
            ->orderBy('id')
            ->each(function (User $admin) use ($ticket, $kind, $title): void {
                $this->notifyUser(
                    $admin,
                    $ticket,
                    $kind.'-admin-'.$admin->id,
                    $title,
                    'A new support ticket was submitted.',
                );
            });
    }

    /**
     * @return array{name: string|null, position: string|null, work_email: string|null, work_phone: string|null}
     */
    private function serializeRequesterForItTech(SupportTicket $ticket): array
    {
        $user = $ticket->requester;
        $employee = $user?->employee;

        return [
            'name' => $user?->name,
            'position' => $employee?->job_title
                ?? ($user?->role?->label()),
            'work_email' => $user?->email,
            'work_phone' => $employee?->primaryPhone(),
        ];
    }

    private function assertItTech(User $user): void
    {
        if (! $user->hasItTechAccess()) {
            throw ValidationException::withMessages([
                'ticket' => 'IT/Tech access is required for this action.',
            ]);
        }
    }

    private function assertAssignedTo(User $user, SupportTicket $ticket): void
    {
        if ($ticket->assigned_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'ticket' => 'Only the assigned IT/Tech technician may perform this action.',
            ]);
        }
    }

    /**
     * @param  list<SupportTicketStatus>  $allowed
     */
    private function assertStatus(SupportTicket $ticket, array $allowed): void
    {
        foreach ($allowed as $status) {
            if ($ticket->status === $status) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'ticket' => 'This ticket cannot be updated from its current status.',
        ]);
    }
}
