<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('update', $employee) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'terminated_on' => ['nullable', 'date', 'required_if:employment_status,'.EmploymentStatus::Terminated->value],
        ];
    }
}
