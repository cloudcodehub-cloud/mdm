<?php

namespace App\Http\Requests;

use App\Rules\NoAuthenticationSecrets;
use Illuminate\Foundation\Http\FormRequest;

class ReplySupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('support_ticket');

        return $ticket !== null && ($this->user()?->can('reply', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000', new NoAuthenticationSecrets],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,doc,docx'],
        ];
    }
}
