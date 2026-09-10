<?php

namespace App\Http\Requests;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');

        return $visit instanceof ScheduledVisit
            && ($this->user()?->can('clockIn', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('location_status');

        if ($status === ClockInLocationStatus::Captured->value) {
            $this->merge([
                'unavailable_reason' => null,
            ]);

            return;
        }

        $this->merge([
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null,
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'unavailable_reason' => $this->blankToNull($this->input('unavailable_reason')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_if:location_status,captured', 'prohibited_unless:location_status,captured'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_if:location_status,captured', 'prohibited_unless:location_status,captured'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'prohibited_unless:location_status,captured'],
            'location_method' => ['required', Rule::enum(ClockInLocationMethod::class)],
            'location_status' => ['required', Rule::enum(ClockInLocationStatus::class)],
            'unavailable_reason' => ['nullable', 'string', 'max:1000', 'required_unless:location_status,captured'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $status = ClockInLocationStatus::from((string) $this->input('location_status'));
            $method = ClockInLocationMethod::from((string) $this->input('location_method'));

            if ($status === ClockInLocationStatus::Captured && $method !== ClockInLocationMethod::BrowserGps) {
                $validator->errors()->add('location_method', 'Captured GPS must use the browser GPS method.');
            }

            if ($status !== ClockInLocationStatus::Captured && $method !== ClockInLocationMethod::GpsUnavailable) {
                $validator->errors()->add('location_method', 'When GPS is not captured, record the GPS-unavailable method.');
            }
        });
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
