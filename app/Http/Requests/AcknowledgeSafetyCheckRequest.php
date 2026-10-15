<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeSafetyCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('recordSafety', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('reason')) {
            $reason = $this->input('reason');
            $this->merge([
                'reason' => is_string($reason) ? trim($reason) : $reason,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
