<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentInvestigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('manage', $incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['finding', 'cause_contributing_factors', 'prevention_plan'] as $field) {
            if ($this->exists($field)) {
                $merge[$field] = $this->blankToNull($this->input($field));
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'finding' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cause_contributing_factors' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'prevention_plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
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
