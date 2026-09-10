<?php

namespace App\Http\Requests;

use App\Models\SkipReason;
use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkipVisitTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $visit instanceof Visit
            && ($this->user()?->can('recordTask', $visit) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $comment = $this->input('skip_comment');

        if (! is_string($comment)) {
            $this->merge(['skip_comment' => null]);

            return;
        }

        $comment = trim($comment);

        $this->merge(['skip_comment' => $comment === '' ? null : $comment]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'skip_reason_id' => ['required', 'integer', Rule::exists('skip_reasons', 'id')->where('is_active', true)],
            'skip_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reason = SkipReason::query()->find($this->integer('skip_reason_id'));

            if (! $reason instanceof SkipReason) {
                return;
            }

            if ($reason->requiresExplanation() && $this->input('skip_comment') === null) {
                $validator->errors()->add(
                    'skip_comment',
                    $reason->code === SkipReason::CLIENT_REFUSED
                        ? 'Record the client refusal explanation.'
                        : 'A comment is required for this skip reason.',
                );
            }
        });
    }
}
