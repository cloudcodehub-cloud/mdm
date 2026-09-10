<?php

namespace App\Http\Requests;

use App\Enums\TrainingStatus;
use App\Models\EmployeeTraining;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $training = $this->route('employee_training') ?? $this->route('employeeTraining');

        if ($training instanceof EmployeeTraining) {
            return $this->user()?->can('update', $training) ?? false;
        }

        return $this->user()?->can('create', EmployeeTraining::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'completed_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:completed_on'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'status' => ['required', Rule::enum(TrainingStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
