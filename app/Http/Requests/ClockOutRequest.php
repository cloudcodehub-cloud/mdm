<?php

namespace App\Http\Requests;

use App\Enums\GpsFallbackReason;
use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClockOutRequest extends FormRequest
{
    use Concerns\ValidatesLocationAttestation;

    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('clockOut', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
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

        $merge = [
            'acknowledge_unfinished_required' => $this->boolean('acknowledge_unfinished_required'),
        ];

        if ($this->exists('visit_notes')) {
            $merge['visit_notes'] = $this->blankToNull($this->input('visit_notes'));
        }

        if ($this->exists('handover_note')) {
            $merge['handover_note'] = $this->blankToNull($this->input('handover_note'));
        }

        $this->merge($merge);
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
            'visit_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'handover_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'acknowledge_unfinished_required' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->afterLocationAttestation($validator));
    }
}
