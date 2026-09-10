<?php

namespace App\Http\Requests;

use App\Enums\CarePlanStatus;
use App\Models\CarePlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $carePlan = $this->route('care_plan') ?? $this->route('carePlan');

        if ($carePlan instanceof CarePlan) {
            return $this->user()?->can('update', $carePlan) ?? false;
        }

        return $this->user()?->can('create', CarePlan::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(CarePlanStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
