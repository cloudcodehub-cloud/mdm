<?php

namespace App\Http\Requests;

use App\Models\AttendanceCorrection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $correction = $this->route('attendance_correction');

        if (! $correction instanceof AttendanceCorrection) {
            return false;
        }

        $ability = str_contains((string) $this->route()?->getName(), 'reject')
            ? 'reject'
            : 'approve';

        return $this->user()?->can($ability, $correction) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
