<?php

namespace App\Http\Requests;

use App\Models\VisitException;
use Illuminate\Foundation\Http\FormRequest;

class ReopenVisitExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var VisitException $exception */
        $exception = $this->route('visit_exception');

        return $this->user()?->can('reopen', $exception) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reopen_reason') && is_string($this->input('reopen_reason'))) {
            $this->merge(['reopen_reason' => trim($this->input('reopen_reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{reopen_reason: string}
     */
    public function payload(): array
    {
        return [
            'reopen_reason' => (string) $this->input('reopen_reason'),
        ];
    }
}
