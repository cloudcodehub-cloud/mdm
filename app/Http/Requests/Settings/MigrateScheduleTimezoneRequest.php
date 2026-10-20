<?php

namespace App\Http\Requests\Settings;

use App\Services\SettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MigrateScheduleTimezoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(SettingsService::class)->current()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_timezone' => ['required', 'string', 'timezone:all'],
            'schedule_migration_reason' => ['required', 'string', 'max:1000'],
            'confirm_schedule_migration' => ['accepted'],
            'migration_effective_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (trim((string) $this->input('schedule_migration_reason', '')) === '') {
                $validator->errors()->add(
                    'schedule_migration_reason',
                    'A Reason for Change is required for schedule timezone migration.',
                );
            }
        });
    }
}
