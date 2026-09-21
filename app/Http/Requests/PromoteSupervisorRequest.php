<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PromoteSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSupervisors', Employee::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }

    public function employee(): Employee
    {
        return Employee::query()->findOrFail((int) $this->validated('employee_id'));
    }
}
