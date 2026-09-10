<?php

namespace App\Http\Requests;

use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
        $this->prepareLocationAttestation();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->locationAttestationRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->afterLocationAttestation($validator));
    }
}
