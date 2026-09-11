<?php

namespace App\Http\Requests;

use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceScheduledVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');

        return $visit instanceof ScheduledVisit
            && ($this->user()?->can('update', $visit) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'mark_call_off' => ['sometimes', 'boolean'],
        ];
    }
}
