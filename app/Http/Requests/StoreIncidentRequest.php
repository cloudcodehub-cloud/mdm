<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('create', [\App\Models\Incident::class, $visit]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'injured' => $this->boolean('injured'),
            'intervention' => $this->boolean('intervention'),
            'supervisor_notified' => $this->boolean('supervisor_notified'),
            'submit' => $this->boolean('submit'),
            'original_category' => $this->blankToNull($this->input('original_category')),
            'original_subcategory' => $this->blankToNull($this->input('original_subcategory')),
            'summary' => $this->blankToNull($this->input('summary')),
            'immediate_action' => $this->blankToNull($this->input('immediate_action')),
            'additional_notes' => $this->blankToNull($this->input('additional_notes')),
            'injury_type' => $this->blankToNull($this->input('injury_type')),
            'injury_body_location' => $this->blankToNull($this->input('injury_body_location')),
            'injury_medical_check' => $this->blankToNull($this->input('injury_medical_check')),
            'injury_notes' => $this->blankToNull($this->input('injury_notes')),
            'intervention_type' => $this->blankToNull($this->input('intervention_type')),
            'intervention_notes' => $this->blankToNull($this->input('intervention_notes')),
            'intervention_started_at' => $this->blankToNull($this->input('intervention_started_at')),
            'intervention_stopped_at' => $this->blankToNull($this->input('intervention_stopped_at')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categories = array_keys(config('mdm.incident.categories', []));
        $submit = $this->boolean('submit');

        return [
            'occurred_at' => [$submit ? 'required' : 'nullable', 'date'],
            'original_category' => [$submit ? 'required' : 'nullable', 'string', 'max:120', Rule::in($categories)],
            'original_subcategory' => ['nullable', 'string', 'max:120'],
            'summary' => [$submit ? 'required' : 'nullable', 'string', 'max:5000'],
            'immediate_action' => [$submit ? 'required' : 'nullable', 'string', 'max:5000'],
            'injured' => ['required', 'boolean'],
            'injury_type' => ['nullable', 'string', 'max:120'],
            'injury_body_location' => ['nullable', 'string', 'max:120'],
            'injury_medical_check' => ['nullable', 'string', 'max:255'],
            'injury_notes' => ['nullable', 'string', 'max:5000'],
            'intervention' => ['required', 'boolean'],
            'intervention_type' => ['nullable', 'string', 'max:120'],
            'intervention_started_at' => ['nullable', 'date'],
            'intervention_stopped_at' => ['nullable', 'date'],
            'intervention_notes' => ['nullable', 'string', 'max:5000'],
            'supervisor_notified' => ['required', 'boolean'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'submit' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'location_method' => ['nullable', 'string', 'max:32'],
            'location_status' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('submit')) {
                return;
            }

            $category = (string) $this->input('original_category');
            $sub = $this->input('original_subcategory');
            /** @var array<string, list<string>> $map */
            $map = config('mdm.incident.categories', []);
            $allowed = $map[$category] ?? [];

            if ($allowed !== [] && filled($sub) && ! in_array($sub, $allowed, true)) {
                $validator->errors()->add('original_subcategory', 'Select a valid subcategory for the chosen category.');
            }
        });
    }

    private function blankToNull(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
