<?php

namespace App\Http\Requests;

use App\Enums\PayrollPeriodScope;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finalize', PayrollPeriod::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'scope' => ['nullable', Rule::enum(PayrollPeriodScope::class)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'scheduled_visit_id' => ['nullable', 'integer', 'exists:scheduled_visits,id'],
            'finalize_note' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'period_start' => (string) $this->input('period_start'),
            'period_end' => (string) $this->input('period_end'),
            'scope' => $this->input('scope', PayrollPeriodScope::Period->value),
            'employee_id' => $this->input('employee_id'),
            'scheduled_visit_id' => $this->input('scheduled_visit_id'),
            'finalize_note' => $this->input('finalize_note'),
            'confirm' => true,
        ];
    }
}
