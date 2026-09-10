<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\EmployeeFormRules;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use EmployeeFormRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
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
        return $this->employeeFieldRules(creating: true);
    }
}
