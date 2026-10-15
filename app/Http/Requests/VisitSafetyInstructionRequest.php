<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisitSafetyInstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'is_urgent' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_urgent')) {
            $this->merge([
                'is_urgent' => $this->boolean('is_urgent'),
            ]);
        }
    }
}
