<?php

namespace App\Http\Requests;

use App\Enums\TaskRecurrence;
use App\Models\CarePlanTaskTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarePlanTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('care_plan_task_template') ?? $this->route('carePlanTaskTemplate');

        if ($template instanceof CarePlanTaskTemplate) {
            return $this->user()?->can('update', $template) ?? false;
        }

        return $this->user()?->can('create', CarePlanTaskTemplate::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'care_plan_id' => ['required', 'integer', 'exists:care_plans,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'recurrence' => ['required', Rule::enum(TaskRecurrence::class)],
            'recurrence_detail' => ['nullable', 'string', 'max:255', 'required_if:recurrence,'.TaskRecurrence::Custom->value],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
