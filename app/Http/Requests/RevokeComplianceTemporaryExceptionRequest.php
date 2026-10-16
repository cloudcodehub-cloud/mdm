<?php

namespace App\Http\Requests;

use App\Models\ComplianceTemporaryException;
use Illuminate\Foundation\Http\FormRequest;

class RevokeComplianceTemporaryExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ComplianceTemporaryException $exception */
        $exception = $this->route('exception');

        return $this->user()?->can('revoke', $exception) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revoke_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
