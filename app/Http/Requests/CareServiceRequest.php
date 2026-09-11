<?php

namespace App\Http\Requests;

use App\Models\CareService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CareServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('care_service') ?? $this->route('careService');

        if ($service instanceof CareService) {
            return $this->user()?->can('update', $service) ?? false;
        }

        return $this->user()?->can('create', CareService::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'note_required' => ['sometimes', 'boolean'],
            'supervisor_review_expected' => ['sometimes', 'boolean'],
            'payer_code' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'recommended_bundle_ids' => ['sometimes', 'array'],
            'recommended_bundle_ids.*' => ['integer', 'exists:task_catalog_bundles,id'],
            'recommended_item_ids' => ['sometimes', 'array'],
            'recommended_item_ids.*' => ['integer', 'exists:task_catalog_items,id'],
        ];
    }
}
