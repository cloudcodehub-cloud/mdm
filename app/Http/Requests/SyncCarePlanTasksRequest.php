<?php

namespace App\Http\Requests;

use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use App\Models\CarePlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCarePlanTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $carePlan = $this->route('care_plan') ?? $this->route('carePlan');

        return $carePlan instanceof CarePlan
            && ($this->user()?->can('update', $carePlan) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tasks' => ['present', 'array'],
            'tasks.*.id' => ['nullable', 'integer', 'exists:care_plan_task_templates,id'],
            'tasks.*.catalog_item_id' => ['nullable', 'integer', 'exists:task_catalog_items,id'],
            'tasks.*.title' => ['nullable', 'string', 'max:255'],
            'tasks.*.instructions' => ['nullable', 'string'],
            'tasks.*.recurrence' => ['nullable', Rule::enum(TaskRecurrence::class)],
            'tasks.*.recurrence_detail' => ['nullable', 'string', 'max:255'],
            'tasks.*.weekdays' => ['nullable', 'array'],
            'tasks.*.weekdays.*' => ['integer', 'min:0', 'max:6'],
            'tasks.*.interval_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tasks.*.preferred_timing' => ['nullable', Rule::enum(TaskPreferredTiming::class)],
            'tasks.*.is_required' => ['sometimes', 'boolean'],
            'tasks.*.note_required' => ['sometimes', 'boolean'],
            'tasks.*.can_skip' => ['sometimes', 'boolean'],
            'tasks.*.is_critical' => ['sometimes', 'boolean'],
        ];
    }
}
