<?php

namespace App\Http\Requests;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;

class ReopenPayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollPeriod $period */
        $period = $this->route('payroll_period');

        return $this->user()?->can('reopen', $period) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reopen_reason')) {
            $this->merge([
                'reopen_reason' => is_string($this->input('reopen_reason'))
                    ? trim($this->input('reopen_reason'))
                    : $this->input('reopen_reason'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array{reopen_reason: string, confirm: bool}
     */
    public function payload(): array
    {
        return [
            'reopen_reason' => (string) $this->input('reopen_reason'),
            'confirm' => true,
        ];
    }
}
