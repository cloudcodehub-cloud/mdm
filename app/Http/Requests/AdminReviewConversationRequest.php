<?php

namespace App\Http\Requests;

use App\Enums\AdminMessageReviewPurpose;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReviewConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            && ($this->user()?->can('review', $conversation) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(AdminMessageReviewPurpose::class)],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
