<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Services\IncidentWorkflowService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('amend', $incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'field_key' => $this->blankToNull($this->input('field_key')),
            'new_content' => $this->blankToNull($this->input('new_content')),
            'reason' => $this->blankToNull($this->input('reason')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string', Rule::in(IncidentWorkflowService::AMENDABLE_FIELDS)],
            'new_content' => ['required', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:1000'],
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
