<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReassignSupervisorCaseloadRequest extends FormRequest
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
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['integer', 'distinct', 'exists:clients,id'],
            'replacement_employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function clientIds(): array
    {
        return array_values(array_map('intval', $this->validated('client_ids')));
    }

    public function replacement(): Employee
    {
        return Employee::query()->findOrFail((int) $this->validated('replacement_employee_id'));
    }
}
