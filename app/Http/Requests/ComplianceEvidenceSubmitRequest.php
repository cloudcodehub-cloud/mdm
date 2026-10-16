<?php

namespace App\Http\Requests;

use App\Enums\CredentialType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplianceEvidenceSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return $user->can('view', $employee);
        }

        return $user->isDsp() && $user->employee?->is($employee);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kind = $this->input('kind');

        if ($kind === 'training') {
            return [
                'kind' => ['required', 'in:training'],
                'title' => ['required', 'string', 'max:160'],
                'provider' => ['nullable', 'string', 'max:160'],
                'completed_on' => ['nullable', 'date'],
                'expires_on' => ['nullable', 'date'],
                'hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'kind' => ['required', 'in:credential'],
            'type' => ['required', Rule::enum(CredentialType::class)],
            'name' => ['required', 'string', 'max:160'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'credential_number' => ['nullable', 'string', 'max:120'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
