<?php

namespace App\Http\Requests;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;

class ReFinalizePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollPeriod $period */
        $period = $this->route('payroll_period');

        return $this->user()?->can('reFinalize', $period) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'finalize_note' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array{finalize_note: string|null, confirm: bool}
     */
    public function payload(): array
    {
        return [
            'finalize_note' => is_string($this->input('finalize_note'))
                ? trim($this->input('finalize_note'))
                : null,
            'confirm' => true,
        ];
    }
}
