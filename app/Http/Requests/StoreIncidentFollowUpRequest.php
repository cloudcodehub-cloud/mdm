<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('manage', $incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action_type' => $this->blankToNull($this->input('action_type')),
            'due_on' => $this->blankToNull($this->input('due_on')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', 'max:255'],
            'assigned_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'due_on' => ['nullable', 'date'],
        ];
    }

    private function blankToNull(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
