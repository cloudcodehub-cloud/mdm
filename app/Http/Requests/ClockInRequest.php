<?php

namespace App\Http\Requests;

use App\Enums\GpsFallbackReason;
use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClockInRequest extends FormRequest
{
    use Concerns\ValidatesLocationAttestation;

    public function authorize(): bool
    {
        $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');

        return $visit instanceof ScheduledVisit
            && ($this->user()?->can('clockIn', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('early_start_confirmed')) {
            $this->merge([
                'early_start_confirmed' => filter_var(
                    $this->input('early_start_confirmed'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false,
            ]);
        }

        $status = $this->input('location_status');

        if ($status !== null && $status !== 'captured') {
            $code = $this->input('gps_fallback_reason');
            $reason = is_string($code) ? GpsFallbackReason::tryFrom($code) : null;
            $note = $this->blankToNull($this->input('gps_fallback_note'));

            if ($reason !== null) {
                $this->merge([
                    'gps_fallback_note' => $reason->requiresNote() ? $note : null,
                    'unavailable_reason' => $reason->requiresNote()
                        ? $note
                        : $reason->label(),
                ]);
            }
        }

        $this->prepareLocationAttestation();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $captured = $this->input('location_status') === 'captured';

        return [
            ...$this->locationAttestationRules(),
            'gps_fallback_reason' => [
                Rule::excludeIf($captured),
                'required_unless:location_status,captured',
                Rule::enum(GpsFallbackReason::class),
            ],
            'gps_fallback_note' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => ! $captured
                    && $this->input('gps_fallback_reason') === GpsFallbackReason::Other->value),
            ],
            'early_start_confirmed' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->afterLocationAttestation($validator));
    }
}
