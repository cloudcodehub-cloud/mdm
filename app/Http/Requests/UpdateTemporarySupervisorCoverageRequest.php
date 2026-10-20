<?php

namespace App\Http\Requests;

use App\Enums\TemporarySupervisorCoverageMode;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemporarySupervisorCoverageRequest extends FormRequest
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
            'mode' => ['nullable', Rule::enum(TemporarySupervisorCoverageMode::class)],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'starts_at' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'mode' => $this->input('mode'),
            'client_ids' => $this->input('client_ids'),
            'employee_ids' => $this->input('employee_ids'),
            'starts_at' => $this->input('starts_at'),
            'ends_at' => $this->input('ends_at'),
            'note' => $this->input('note'),
        ];
    }
}
