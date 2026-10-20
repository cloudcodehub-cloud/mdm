<?php

namespace App\Http\Requests;

use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduledVisit = $this->route('scheduled_visit');

        return $scheduledVisit instanceof ScheduledVisit
            && ($this->user()?->can('create', [AttendanceCorrection::class, $scheduledVisit]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'requested_clocked_in_at' => ['nullable', 'string', 'max:32'],
            'requested_clocked_out_at' => ['nullable', 'string', 'max:32'],
            'requested_visit_notes' => ['nullable', 'string', 'max:10000'],
            'requested_handover_note' => ['nullable', 'string', 'max:10000'],
            'requested_clock_in_location_status' => ['nullable', 'string', 'max:64'],
            'requested_clock_in_unavailable_reason' => ['nullable', 'string', 'max:2000'],
            'requested_clock_out_location_status' => ['nullable', 'string', 'max:64'],
            'requested_clock_out_unavailable_reason' => ['nullable', 'string', 'max:2000'],
            'is_evv_reconstruction' => ['sometimes', 'boolean'],
            'task_corrections' => ['sometimes', 'array'],
            'task_corrections.*.visit_task_id' => ['required_with:task_corrections', 'integer', 'exists:visit_tasks,id'],
            'task_corrections.*.requested_status' => ['required_with:task_corrections', 'string', 'in:pending,completed,skipped'],
            'task_corrections.*.requested_skip_reason_id' => ['nullable', 'integer', 'exists:skip_reasons,id'],
            'task_corrections.*.requested_skip_comment' => ['nullable', 'string', 'max:2000'],
            'task_corrections.*.requested_completion_note' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
