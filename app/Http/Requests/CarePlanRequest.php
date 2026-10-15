<?php

namespace App\Http\Requests;

use App\Enums\CarePlanStatus;
use App\Models\CarePlan;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $carePlan = $this->route('care_plan') ?? $this->route('carePlan');

        if ($carePlan instanceof CarePlan) {
            return $user->can('update', $carePlan);
        }

        $client = Client::query()->find($this->input('client_id'));

        return $client instanceof Client && $user->can('manageCarePlan', $client);
    }

    protected function prepareForValidation(): void
    {
        $client = $this->route('client');

        if ($client instanceof Client) {
            $this->merge(['client_id' => $client->id]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(CarePlanStatus::class)],
            'notes' => ['nullable', 'string'],
            'effective_on' => ['nullable', 'date'],
            'revision_reason' => ['nullable', 'string', 'max:1000'],
            'timed_check_interval_minutes' => [
                'nullable',
                'integer',
                Rule::in(config('mdm.safety_check.allowed_intervals', [5, 10, 15, 30])),
            ],
            'supervision_instruction' => ['nullable', 'string', 'max:5000'],
            'tasks' => ['sometimes', 'array'],
            'tasks.*.id' => ['nullable', 'integer', 'exists:care_plan_task_templates,id'],
            'tasks.*.catalog_item_id' => ['nullable', 'integer', 'exists:task_catalog_items,id'],
            'tasks.*.title' => ['nullable', 'string', 'max:255'],
            'tasks.*.instructions' => ['nullable', 'string'],
            'tasks.*.recurrence' => ['nullable', 'string'],
            'tasks.*.recurrence_detail' => ['nullable', 'string', 'max:255'],
            'tasks.*.weekdays' => ['nullable', 'array'],
            'tasks.*.weekdays.*' => ['integer', 'min:0', 'max:6'],
            'tasks.*.interval_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tasks.*.preferred_timing' => ['nullable', 'string'],
            'tasks.*.is_required' => ['sometimes', 'boolean'],
            'tasks.*.note_required' => ['sometimes', 'boolean'],
            'tasks.*.can_skip' => ['sometimes', 'boolean'],
            'tasks.*.is_critical' => ['sometimes', 'boolean'],
        ];
    }
}
