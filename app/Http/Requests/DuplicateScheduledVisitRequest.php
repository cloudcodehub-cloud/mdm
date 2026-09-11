<?php

namespace App\Http\Requests;

use App\Models\ScheduledVisit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DuplicateScheduledVisitRequest extends FormRequest
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
            'preset' => ['nullable', 'in:tomorrow,next_week'],
            'service_date' => ['required_without:preset', 'nullable', 'date'],
        ];
    }
}
