<?php

namespace App\Models;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketUrgency;
use App\Enums\SupportTicketWorkImpact;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Internal Agency Support Ticket (MDM 12). Distinct from clinical Incidents and HR security incidents.
 *
 * @property int $id
 * @property string $ticket_number
 * @property int $requester_user_id
 * @property int|null $assigned_user_id
 * @property SupportTicketStatus $status
 * @property SupportTicketCategory $category
 * @property string $subject
 * @property string $description
 * @property SupportTicketWorkImpact $work_impact
 * @property SupportTicketUrgency $urgency
 * @property string|null $resolution_note
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $duplicate_of_ticket_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 * @property-read User|null $assignee
 * @property-read SupportTicket|null $duplicateOf
 * @property-read Collection<int, SupportTicketReply> $replies
 * @property-read Collection<int, SupportTicketAttachment> $attachments
 * @property-read Collection<int, SupportTicketHistoryEvent> $historyEvents
 */
#[Fillable([
    'ticket_number',
    'requester_user_id',
    'assigned_user_id',
    'status',
    'category',
    'subject',
    'description',
    'work_impact',
    'urgency',
    'resolution_note',
    'resolved_at',
    'closed_at',
    'cancelled_at',
    'duplicate_of_ticket_id',
])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    public const REOPEN_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'category' => SupportTicketCategory::class,
            'work_impact' => SupportTicketWorkImpact::class,
            'urgency' => SupportTicketUrgency::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_ticket_id');
    }

    /**
     * @return HasMany<SupportTicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<SupportTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<SupportTicketHistoryEvent, $this>
     */
    public function historyEvents(): HasMany
    {
        return $this->hasMany(SupportTicketHistoryEvent::class)->orderBy('created_at');
    }

    public function isWithinReopenWindow(?\DateTimeInterface $now = null): bool
    {
        if ($this->status !== SupportTicketStatus::Resolved || $this->resolved_at === null) {
            return false;
        }

        $now ??= now();

        return Carbon::parse($now)->lessThanOrEqualTo(
            $this->resolved_at->copy()->addDays(self::REOPEN_DAYS)->endOfDay()
        );
    }

    public function reopenDeadline(): ?Carbon
    {
        if ($this->resolved_at === null) {
            return null;
        }

        return $this->resolved_at->copy()->addDays(self::REOPEN_DAYS)->endOfDay();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SupportTicketStatus::Unassigned,
            SupportTicketStatus::InProgress,
            SupportTicketStatus::Resolved,
        ]);
    }
}
