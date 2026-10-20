<?php

namespace App\Http\Requests\Settings;

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Services\SettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'main_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'main_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_format' => ['required', Rule::enum(DateFormat::class)],
            'time_format' => ['required', Rule::enum(TimeFormat::class)],
            'first_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'credential_expiring_soon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'incident_acknowledgment_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            // Hours before scheduled start (MDM 04-29); max 720 = 30 days.
            'staffing_needed_urgency_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'horizontal_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_horizontal_logo' => ['sometimes', 'boolean'],
            // High-risk Official Agency Name change (MDM 11-05 / 11-11).
            'official_name_change_reason' => ['nullable', 'string', 'max:1000'],
            'confirm_official_name_change' => ['sometimes', 'boolean'],
            // Future-effective timezone change (MDM 11-02 / 11-10) — not an immediate overwrite.
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'timezone_effective_at' => ['nullable', 'date'],
            'timezone_change_reason' => ['nullable', 'string', 'max:1000'],
            'confirm_timezone_change' => ['sometimes', 'boolean'],
            'cancel_pending_timezone' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $settings = app(SettingsService::class)->current();
            $officialName = trim((string) $this->input('organization_name', ''));
            $currentOfficial = trim((string) $settings->organization_name);

            if ($officialName !== '' && $officialName !== $currentOfficial) {
                if (! $this->boolean('confirm_official_name_change')) {
                    $validator->errors()->add(
                        'confirm_official_name_change',
                        'Confirm the Official Agency Name change before saving.',
                    );
                }

                if (trim((string) $this->input('official_name_change_reason', '')) === '') {
                    $validator->errors()->add(
                        'official_name_change_reason',
                        'A Reason for Change is required when updating the Official Agency Name.',
                    );
                }
            }

            if ($this->boolean('cancel_pending_timezone')) {
                return;
            }

            $requestedTimezone = trim((string) $this->input('timezone', ''));
            if ($requestedTimezone === '' || $requestedTimezone === $settings->timezone) {
                return;
            }

            if (! $this->boolean('confirm_timezone_change')) {
                $validator->errors()->add(
                    'confirm_timezone_change',
                    'Confirm the Agency Operational Timezone change before saving.',
                );
            }

            if (trim((string) $this->input('timezone_change_reason', '')) === '') {
                $validator->errors()->add(
                    'timezone_change_reason',
                    'A Reason for Change is required for Agency Operational Timezone changes.',
                );
            }

            if (! filled($this->input('timezone_effective_at'))) {
                $validator->errors()->add(
                    'timezone_effective_at',
                    'Provide an effective date/time for the Agency Operational Timezone change.',
                );
            }
        });
    }
}
