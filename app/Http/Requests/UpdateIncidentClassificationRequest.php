<?php

namespace App\Http\Requests;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('manage', $incident) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'official_category' => $this->blankToNull($this->input('official_category')),
            'official_subcategory' => $this->blankToNull($this->input('official_subcategory')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categories = array_keys(config('mdm.incident.categories', []));

        return [
            'official_category' => ['required', 'string', 'max:120', Rule::in($categories)],
            'official_subcategory' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $category = (string) $this->input('official_category');
            $sub = $this->input('official_subcategory');
            /** @var array<string, list<string>> $map */
            $map = config('mdm.incident.categories', []);
            $allowed = $map[$category] ?? [];

            if (filled($sub) && ! in_array($sub, $allowed, true)) {
                $validator->errors()->add('official_subcategory', 'Select a valid subcategory for the chosen category.');
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
