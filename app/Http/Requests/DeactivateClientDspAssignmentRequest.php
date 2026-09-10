<?php

namespace App\Http\Requests;

use App\Models\ClientDspAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateClientDspAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof ClientDspAssignment
            && ($this->user()?->can('update', $assignment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ended_on' => ['nullable', 'date'],
        ];
    }
}
