<?php

namespace App\Http\Requests;

use App\Enums\AvailabilityRequestType;
use App\Enums\PreferredDaypart;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DspAvailabilityRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\DspAvailabilityRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AvailabilityRequestType::class)],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
            'payload' => ['required', 'array'],
            'payload.days' => ['required_if:type,weekly', 'array'],
            'payload.days.*.weekday' => ['required_with:payload.days', 'integer', 'min:0', 'max:6'],
            'payload.days.*.is_available' => ['sometimes', 'boolean'],
            'payload.days.*.starts_at' => ['nullable', 'date_format:H:i'],
            'payload.days.*.ends_at' => ['nullable', 'date_format:H:i'],
            'payload.days.*.preferred_daypart' => ['nullable', Rule::enum(PreferredDaypart::class)],
            'payload.exception_date' => ['required_if:type,exception', 'date'],
            'payload.is_available' => ['sometimes', 'boolean'],
            'payload.starts_at' => ['nullable', 'date_format:H:i'],
            'payload.ends_at' => ['nullable', 'date_format:H:i'],
            'payload.note' => ['nullable', 'string'],
        ];
    }
}
