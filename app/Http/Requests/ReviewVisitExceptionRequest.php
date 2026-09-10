<?php

namespace App\Http\Requests;

use App\Models\VisitException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewVisitExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exception = $this->route('visit_exception');

        return $exception instanceof VisitException
            && ($this->user()?->can('review', $exception) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
