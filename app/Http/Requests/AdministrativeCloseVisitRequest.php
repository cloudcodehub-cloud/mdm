<?php

namespace App\Http\Requests;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class AdministrativeCloseVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('administrativelyClose', $visit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_ended_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_ended_at.required' => 'Enter the effective service end time.',
            'reason.required' => 'Enter a reason for administrative close.',
            'reason.min' => 'Enter a reason for administrative close.',
        ];
    }
}
