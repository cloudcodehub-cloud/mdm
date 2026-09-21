<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RevokeSupervisorRequest extends FormRequest
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
            'replacement_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function replacement(): ?Employee
    {
        $id = $this->validated('replacement_employee_id');

        if ($id === null || $id === '') {
            return null;
        }

        return Employee::query()->find((int) $id);
    }
}
