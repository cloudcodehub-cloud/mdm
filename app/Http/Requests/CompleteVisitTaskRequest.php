<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteVisitTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('recordTask', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $note = $this->input('completion_note');

        if (! is_string($note)) {
            $this->merge(['completion_note' => null]);

            return;
        }

        $note = trim($note);

        $this->merge(['completion_note' => $note === '' ? null : $note]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completion_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
