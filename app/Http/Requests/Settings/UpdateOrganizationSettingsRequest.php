<?php

namespace App\Http\Requests\Settings;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Services\SettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationSettingsRequest extends FormRequest
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
            'organization_name' => ['required', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'date_format' => ['required', Rule::enum(DateFormat::class)],
            'time_format' => ['required', Rule::enum(TimeFormat::class)],
            'first_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'credential_expiring_soon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
