<?php

namespace App\Http\Requests;

use App\Models\IncidentFollowUp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReassignIncidentFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('incident_follow_up');

        return $followUp instanceof IncidentFollowUp
            && $followUp->incident !== null
            && ($this->user()?->can('manage', $followUp->incident) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }
}
