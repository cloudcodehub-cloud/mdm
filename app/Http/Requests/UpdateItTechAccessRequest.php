<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItTechAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageItTechAccess', SupportTicket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
