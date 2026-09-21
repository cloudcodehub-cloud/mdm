<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReassignSupervisorTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('manageSupervisors', Employee::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'replacement_employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function employeeIds(): array
    {
        return array_values(array_map('intval', $this->validated('employee_ids')));
    }

    public function replacement(): Employee
    {
        return Employee::query()->findOrFail((int) $this->validated('replacement_employee_id'));
    }
}
