<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use App\Services\SettingsService;
use App\Support\OperationalReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ViewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', OperationalReport::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
            'supervisor_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}
     */
    public function filters(ReportType $type, SettingsService $settings): array
    {
        $today = $settings->today();
        $defaultFrom = $type->defaultDateRange()
            ? $settings->localNow()->subDays(6)->toDateString()
            : '';
        $defaultTo = $type->defaultDateRange() ? $today : '';

        return [
            'from' => $this->string('from')->trim()->value() ?: $defaultFrom,
            'to' => $this->string('to')->trim()->value() ?: ($this->string('from')->trim()->value() !== '' ? $this->string('from')->trim()->value() : $defaultTo),
            'employee_id' => $this->string('employee_id')->value(),
            'client_id' => $this->string('client_id')->value(),
            'supervisor_id' => $this->user()?->isAdmin() ? $this->string('supervisor_id')->value() : '',
            'status' => $this->string('status')->value(),
        ];
    }
}
