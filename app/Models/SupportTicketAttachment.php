<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $support_ticket_reply_id
 * @property int $uploaded_by_user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property bool $is_initial
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 * @property-read SupportTicketReply|null $reply
 * @property-read User $uploader
 */
#[Fillable([
    'support_ticket_id',
    'support_ticket_reply_id',
    'uploaded_by_user_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'is_initial',
])]
class SupportTicketAttachment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_initial' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return BelongsTo<SupportTicketReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'support_ticket_reply_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
