<?php

namespace App\Http\Requests;

use App\Enums\CriticalAlertCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriticalAlertRequest extends FormRequest
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
            'category' => ['required', 'string', Rule::enum(CriticalAlertCategory::class)],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
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
