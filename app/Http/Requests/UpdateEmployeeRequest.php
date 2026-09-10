<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\EmployeeFormRules;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeFormRules;

    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('update', $employee) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareEmployeePayload();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : null;

        return $this->employeeFieldRules($employeeId, creating: false);
    }
}
