<?php

namespace App\Http\Requests;

use App\Models\ShiftTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('shift_template') ?? $this->route('shiftTemplate');

        if ($template instanceof ShiftTemplate) {
            return $this->user()?->can('update', $template) ?? false;
        }

        return $this->user()?->can('create', ShiftTemplate::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $template = $this->route('shift_template') ?? $this->route('shiftTemplate');
        $templateId = $template instanceof ShiftTemplate ? $template->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shift_templates', 'code')->ignore($templateId),
            ],
            'starts_at' => ['required', 'date_format:H:i:s'],
            'ends_at' => ['required', 'date_format:H:i:s'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
