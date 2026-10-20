<?php

namespace App\Http\Requests;

use App\Models\ScheduledVisit;
use Illuminate\Foundation\Http\FormRequest;

class ReclassifyMissedVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ScheduledVisit $scheduledVisit */
        $scheduledVisit = $this->route('scheduled_visit');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return ($user->isAdmin() || $user->isSupervisor())
            && $user->can('update', $scheduledVisit);
    }

    protected function prepareForValidation(): void
    {
        foreach (['reason', 'evidence_note'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'evidence_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{reason: string, evidence_note: string|null}
     */
    public function payload(): array
    {
        $evidence = $this->input('evidence_note');

        return [
            'reason' => (string) $this->input('reason'),
            'evidence_note' => is_string($evidence) && trim($evidence) !== '' ? trim($evidence) : null,
        ];
    }
}
