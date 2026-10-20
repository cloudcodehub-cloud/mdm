<?php

namespace App\Http\Controllers;

use App\Models\SupportTicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketAttachmentController extends Controller
{
    public function show(SupportTicketAttachment $support_ticket_attachment): StreamedResponse
    {
        $support_ticket_attachment->loadMissing('ticket');
        $this->authorize('viewFull', $support_ticket_attachment->ticket);

        $disk = Storage::disk($support_ticket_attachment->disk);
        abort_unless($disk->exists($support_ticket_attachment->path), 404);

        return $disk->response(
            $support_ticket_attachment->path,
            $support_ticket_attachment->original_name,
            [
                'Content-Type' => $support_ticket_attachment->mime_type ?: 'application/octet-stream',
            ],
        );
    }
}
