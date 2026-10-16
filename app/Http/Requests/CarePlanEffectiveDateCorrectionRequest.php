<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CarePlanEffectiveDateCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $carePlan = $this->route('care_plan') ?? $this->route('carePlan');

        return $this->user()?->can('correctEffectiveDate', $carePlan) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'corrected_starts_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
