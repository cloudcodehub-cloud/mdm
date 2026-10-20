<?php

namespace App\Http\Requests;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketWorkImpact;
use App\Models\SupportTicket;
use App\Rules\NoAuthenticationSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupportTicket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(SupportTicketCategory::class)],
            'subject' => ['required', 'string', 'max:160', new NoAuthenticationSecrets],
            'description' => ['required', 'string', 'max:5000', new NoAuthenticationSecrets],
            'work_impact' => ['required', Rule::enum(SupportTicketWorkImpact::class)],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,doc,docx'],
        ];
    }
}
