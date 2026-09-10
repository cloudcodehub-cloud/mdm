<?php

namespace App\Http\Requests;

use App\Enums\ScheduledVisitStatus;
use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduledVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');

        if ($visit instanceof ScheduledVisit) {
            return $this->user()?->can('update', $visit) ?? false;
        }

        return $this->user()?->can('create', ScheduledVisit::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],
            'shift_template_id' => ['nullable', 'integer', 'exists:shift_templates,id', 'required_without_all:starts_at,ends_at'],
            'service_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i:s', 'required_without:shift_template_id'],
            'ends_at' => ['nullable', 'date_format:H:i:s', 'required_without:shift_template_id'],
            'service_type' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ScheduledVisitStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
