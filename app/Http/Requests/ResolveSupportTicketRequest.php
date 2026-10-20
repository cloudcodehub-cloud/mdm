<?php

namespace App\Http\Requests;

use App\Rules\NoAuthenticationSecrets;
use Illuminate\Foundation\Http\FormRequest;

class ResolveSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('support_ticket');

        return $ticket !== null && ($this->user()?->can('resolve', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'max:2000', new NoAuthenticationSecrets],
        ];
    }
}
