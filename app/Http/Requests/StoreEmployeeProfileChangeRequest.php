<?php

namespace App\Http\Requests;

use App\Enums\EmployeeProfileChangeField;
use App\Models\EmployeeProfileChangeRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeProfileChangeRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];

        foreach (EmployeeProfileChangeField::values() as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
