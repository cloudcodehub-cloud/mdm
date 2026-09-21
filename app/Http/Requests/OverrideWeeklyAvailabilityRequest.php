<?php

namespace App\Http\Requests;

use App\Enums\PreferredDaypart;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideWeeklyAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isAdmin()) {
            return false;
        }

        $employeeId = (int) $this->input('employee_id');

        if ($employeeId <= 0) {
            return true;
        }

        $employee = Employee::query()->find($employeeId);

        if ($employee === null) {
            return true;
        }

        return $user->can('overrideWeeklyAvailability', $employee);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'days.*.is_available' => ['sometimes', 'boolean'],
            'days.*.starts_at' => ['nullable', 'date_format:H:i'],
            'days.*.ends_at' => ['nullable', 'date_format:H:i'],
            'days.*.preferred_daypart' => ['nullable', Rule::enum(PreferredDaypart::class)],
        ];
    }

    public function employee(): Employee
    {
        return Employee::query()->findOrFail((int) $this->integer('employee_id'));
    }
}
