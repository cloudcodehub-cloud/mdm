<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkDuplicateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('support_ticket');

        return $ticket !== null && ($this->user()?->can('markDuplicate', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ticket = $this->route('support_ticket');

        return [
            'duplicate_of_ticket_id' => [
                'required',
                'integer',
                Rule::exists('support_tickets', 'id')->where(function ($query) use ($ticket): void {
                    if ($ticket instanceof SupportTicket) {
                        $query->where('id', '!=', $ticket->id);
                    }
                }),
            ],
        ];
    }

    public function canonicalTicket(): SupportTicket
    {
        return SupportTicket::query()->findOrFail((int) $this->validated('duplicate_of_ticket_id'));
    }
}
