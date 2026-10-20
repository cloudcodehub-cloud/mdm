<?php

namespace App\Http\Controllers;

use App\Models\ConversationMessageAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationAttachmentController extends Controller
{
    public function show(ConversationMessageAttachment $conversation_message_attachment): StreamedResponse
    {
        $conversation_message_attachment->loadMissing('message.conversation');
        $user = request()->user();
        abort_unless($user !== null, 401);
        $this->authorize('view', $conversation_message_attachment);

        $disk = Storage::disk($conversation_message_attachment->disk);
        abort_unless($disk->exists($conversation_message_attachment->path), 404);

        return $disk->response(
            $conversation_message_attachment->path,
            $conversation_message_attachment->original_name,
            [
                'Content-Type' => $conversation_message_attachment->mime_type ?: 'application/octet-stream',
            ],
        );
    }
}
