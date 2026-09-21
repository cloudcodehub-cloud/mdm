<?php

namespace App\Http\Requests;

use App\Models\VisitException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FollowUpVisitExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exception = $this->route('visit_exception');

        return $exception instanceof VisitException
            && ($this->user()?->can('followUp', $exception) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
