<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('updateNotes', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'visit_notes' => $this->blankToNull($this->input('visit_notes')),
            'handover_note' => $this->blankToNull($this->input('handover_note')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visit_notes' => ['nullable', 'string', 'max:5000'],
            'handover_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
