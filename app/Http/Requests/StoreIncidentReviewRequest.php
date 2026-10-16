<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentReviewRequest extends FormRequest
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
            'note' => $this->blankToNull($this->input('note')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:5000'],
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
