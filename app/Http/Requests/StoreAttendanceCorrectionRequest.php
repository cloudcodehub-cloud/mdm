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
            'requested_clocked_in_at' => ['nullable', 'string', 'max:32'],
            'requested_clocked_out_at' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
