<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReopenIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('reopen', $incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reopen_reason' => $this->blankToNull($this->input('reopen_reason')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'max:2000'],
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
