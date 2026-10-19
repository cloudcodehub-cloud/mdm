<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class HandOffVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('handOff', $visit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'effective_ended_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
            'mark_call_off' => ['sometimes', 'boolean'],
        ];
    }
}
