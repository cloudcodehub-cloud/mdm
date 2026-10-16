<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplianceEvidenceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $decision = $this->input('decision');

        return [
            'decision' => ['required', 'in:verify,reject'],
            'note' => [
                $decision === 'reject' ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }
}
