<?php

namespace App\Http\Requests\Settings;

use App\Support\MobileNavigation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMobileNavShortcutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shortcuts' => ['required', 'array', 'size:'.MobileNavigation::SHORTCUT_COUNT],
            'shortcuts.*' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user === null) {
                $validator->errors()->add('shortcuts', __('Unable to update mobile navigation shortcuts.'));

                return;
            }

            try {
                MobileNavigation::sanitizeShortcutKeys(
                    $user->role,
                    array_values($this->input('shortcuts', [])),
                );
            } catch (\InvalidArgumentException) {
                $validator->errors()->add(
                    'shortcuts',
                    __('Choose three unique destinations you are authorized to open.'),
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    public function shortcutKeys(): array
    {
        /** @var list<string> $shortcuts */
        $shortcuts = array_values($this->validated('shortcuts'));

        return MobileNavigation::sanitizeShortcutKeys($this->user()->role, $shortcuts);
    }
}
