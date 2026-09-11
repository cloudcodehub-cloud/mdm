<?php

namespace App\Http\Requests;

use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientCareSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('manageCarePlan', $client) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['present', 'array'],
            'service_ids.*' => ['integer', 'exists:care_services,id'],
            'starts_on' => ['nullable', 'date'],
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
