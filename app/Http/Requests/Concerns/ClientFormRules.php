<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ClientStatus;
use App\Enums\JobType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ClientFormRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function clientFieldRules(?int $clientId = null): array
    {
        return [
            'client_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('clients', 'client_number')->ignore($clientId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('job_type', JobType::Supervisor->value)),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareClientPayload(): void
    {
        $this->merge([
            'supervisor_id' => $this->filled('supervisor_id') ? $this->input('supervisor_id') : null,
        ]);
    }
}
