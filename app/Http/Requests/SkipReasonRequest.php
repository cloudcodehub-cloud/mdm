<?php

namespace App\Http\Requests;

use App\Models\SkipReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkipReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reason = $this->route('skip_reason') ?? $this->route('skipReason');

        if ($reason instanceof SkipReason) {
            return $this->user()?->can('update', $reason) ?? false;
        }

        return $this->user()?->can('create', SkipReason::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reason = $this->route('skip_reason') ?? $this->route('skipReason');
        $reasonId = $reason instanceof SkipReason ? $reason->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('skip_reasons', 'code')->ignore($reasonId),
            ],
            'requires_comment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
