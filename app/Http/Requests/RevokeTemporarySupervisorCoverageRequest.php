<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class RevokeTemporarySupervisorCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSupervisors', Employee::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('revoke_reason') && is_string($this->input('revoke_reason'))) {
            $this->merge(['revoke_reason' => trim($this->input('revoke_reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revoke_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{revoke_reason: string}
     */
    public function payload(): array
    {
        return [
            'revoke_reason' => (string) $this->input('revoke_reason'),
        ];
    }
}
