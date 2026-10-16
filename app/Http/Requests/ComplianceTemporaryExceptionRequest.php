<?php

namespace App\Http\Requests;

use App\Models\ComplianceRequirement;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplianceTemporaryExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $this->user()?->can('create', [ComplianceTemporaryException::class, $employee]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'compliance_requirement_id' => [
                'required',
                'integer',
                Rule::exists('compliance_requirements', 'id')->where('is_active', true),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function requirement(): ComplianceRequirement
    {
        return ComplianceRequirement::query()->findOrFail((int) $this->validated('compliance_requirement_id'));
    }
}
