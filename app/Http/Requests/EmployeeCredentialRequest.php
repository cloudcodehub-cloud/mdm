<?php

namespace App\Http\Requests;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Models\EmployeeCredential;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $credential = $this->route('employee_credential') ?? $this->route('employeeCredential');

        if ($credential instanceof EmployeeCredential) {
            return $this->user()?->can('update', $credential) ?? false;
        }

        return $this->user()?->can('create', EmployeeCredential::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', Rule::enum(CredentialType::class)],
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'credential_number' => ['nullable', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'status' => ['required', Rule::enum(CredentialStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
