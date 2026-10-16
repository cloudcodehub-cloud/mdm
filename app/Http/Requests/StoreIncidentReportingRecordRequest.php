<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentReportingRecordRequest extends FormRequest
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
            'reported_to' => $this->blankToNull($this->input('reported_to')),
            'person_organization' => $this->blankToNull($this->input('person_organization')),
            'reported_at' => $this->blankToNull($this->input('reported_at')),
            'reported_by_employee_id' => $this->blankToNull($this->input('reported_by_employee_id')),
            'category' => $this->blankToNull($this->input('category')),
            'method' => $this->blankToNull($this->input('method')),
            'reference_number' => $this->blankToNull($this->input('reference_number')),
            'note' => $this->blankToNull($this->input('note')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reported_to' => ['required', 'string', 'max:255'],
            'person_organization' => ['nullable', 'string', 'max:255'],
            'reported_at' => ['nullable', 'date'],
            'reported_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:120'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:5000'],
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
