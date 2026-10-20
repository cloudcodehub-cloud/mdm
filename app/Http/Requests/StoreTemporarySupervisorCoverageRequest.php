<?php

namespace App\Http\Requests;

use App\Enums\TemporarySupervisorCoverageMode;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemporarySupervisorCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSupervisors', Employee::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'covering_supervisor_id' => ['required', 'integer', 'exists:employees,id'],
            'mode' => ['nullable', Rule::enum(TemporarySupervisorCoverageMode::class)],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'starts_at' => ['required', 'string'],
            'ends_at' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'covering_supervisor_id' => (int) $this->input('covering_supervisor_id'),
            'mode' => $this->input('mode', TemporarySupervisorCoverageMode::FullCaseload->value),
            'client_ids' => $this->input('client_ids', []),
            'employee_ids' => $this->input('employee_ids', []),
            'starts_at' => (string) $this->input('starts_at'),
            'ends_at' => (string) $this->input('ends_at'),
            'note' => $this->input('note'),
        ];
    }
}
