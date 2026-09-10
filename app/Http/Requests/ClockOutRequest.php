<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            ...$this->locationAttestationRules(),
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
