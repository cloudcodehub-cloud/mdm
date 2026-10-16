<?php

namespace App\Http\Requests;

use App\Enums\ClientContactRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $this->user()?->can('manageContacts', $client) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(ClientContactRole::class)],
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
