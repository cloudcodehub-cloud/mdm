<?php

namespace App\Enums;

enum SupportTicketHistoryAction: string
{
    case Created = 'created';
    case Claimed = 'claimed';
    case Reassigned = 'reassigned';
    case Takeover = 'takeover';
    case Replied = 'replied';
    case AttachmentAdded = 'attachment_added';
    case Resolved = 'resolved';
    case Reopened = 'reopened';
    case Cancelled = 'cancelled';
    case MarkedDuplicate = 'marked_duplicate';
    case Closed = 'closed';
    case ReturnedToUnassigned = 'returned_to_unassigned';
}
