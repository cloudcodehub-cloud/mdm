<?php

namespace App\Http\Requests;

use App\Models\IncidentFollowUp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteIncidentFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('incident_follow_up');

        return $followUp instanceof IncidentFollowUp
            && $followUp->incident !== null
            && ($this->user()?->can('manage', $followUp->incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'completion_note' => $this->blankToNull($this->input('completion_note')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completion_note' => ['nullable', 'string', 'max:5000'],
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
