<?php

namespace App\Http\Requests;

use App\Enums\ScheduledVisitStatus;
use App\Enums\SeriesEditScope;
use App\Enums\VisitRecurrencePattern;
use App\Models\Client;
use App\Models\ScheduledVisit;
use App\Services\ScheduledVisitService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduledVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');

        if ($visit instanceof ScheduledVisit) {
            return $this->user()?->can('update', $visit) ?? false;
        }

        return $this->user()?->can('create', ScheduledVisit::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $shiftTemplateId = $this->blankToNull($this->input('shift_template_id'));
        $startsAt = $this->blankToNull($this->input('starts_at'));
        $endsAt = $this->blankToNull($this->input('ends_at'));

        if ($this->input('timing_mode') === 'template') {
            $startsAt = null;
            $endsAt = null;
        }

        if ($this->input('timing_mode') === 'custom') {
            $shiftTemplateId = null;
        }

        if ($shiftTemplateId !== null) {
            $startsAt = null;
            $endsAt = null;
        }

        $supervisorId = $this->blankToNull($this->input('supervisor_id'));
        $clientId = $this->input('client_id');

        if (is_numeric($clientId)) {
            $client = Client::query()->find((int) $clientId);
            $user = $this->user();

            if ($client !== null && ($user === null || ! $user->isAdmin() || $supervisorId === null)) {
                $supervisorId = $client->supervisor_id;
            }
        }

        $merge = [
            'supervisor_id' => $supervisorId,
            'shift_template_id' => $shiftTemplateId,
            'starts_at' => $this->normalizeTime($startsAt),
            'ends_at' => $this->normalizeTime($endsAt),
            'notes' => $this->blankToNull($this->input('notes')),
            'cancellation_reason' => $this->blankToNull($this->input('cancellation_reason')),
            'updated_by_user_id' => $this->user()?->id,
            'repeat' => filter_var($this->input('repeat'), FILTER_VALIDATE_BOOLEAN),
        ];

        if ($this->isMethod('post')) {
            $merge['created_by_user_id'] = $this->user()?->id;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],
            'shift_template_id' => ['nullable', 'integer', 'exists:shift_templates,id', 'required_without_all:starts_at,ends_at'],
            'service_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i:s', 'required_without:shift_template_id'],
            'ends_at' => ['nullable', 'date_format:H:i:s', 'required_without:shift_template_id'],
            'service_type' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ScheduledVisitStatus::class)->only([
                ScheduledVisitStatus::Scheduled,
                ScheduledVisitStatus::Cancelled,
                ScheduledVisitStatus::Completed,
            ])],
            'notes' => ['nullable', 'string'],
            'cancellation_reason' => ['nullable', 'string'],
            'series_scope' => ['nullable', Rule::enum(SeriesEditScope::class)],
            'repeat' => ['sometimes', 'boolean'],
            'repeat_pattern' => ['nullable', Rule::enum(VisitRecurrencePattern::class)],
            'repeat_interval' => ['nullable', 'integer', 'min:1', 'max:8'],
            'repeat_ends_on' => ['nullable', 'date', 'after_or_equal:service_date'],
            'repeat_count' => ['nullable', 'integer', 'min:2', 'max:26'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['integer', 'min:0', 'max:6'],
            'created_by_user_id' => ['nullable', 'integer'],
            'updated_by_user_id' => ['nullable', 'integer'],
            'one_off_tasks' => ['sometimes', 'array'],
            'one_off_tasks.*.id' => ['nullable', 'integer'],
            'one_off_tasks.*.title' => ['nullable', 'string', 'max:255'],
            'one_off_tasks.*.instructions' => ['nullable', 'string'],
            'one_off_tasks.*.note_required' => ['sometimes', 'boolean'],
            'one_off_tasks.*.is_required' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user === null) {
                return;
            }

            $visit = $this->route('scheduled_visit') ?? $this->route('scheduledVisit');
            $existing = $visit instanceof ScheduledVisit ? $visit : null;

            try {
                app(ScheduledVisitService::class)->assertSchedulable($user, $this->only([
                    'client_id',
                    'employee_id',
                    'supervisor_id',
                    'shift_template_id',
                    'service_date',
                    'starts_at',
                    'ends_at',
                    'service_type',
                    'status',
                    'notes',
                ]), $existing);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        return $value;
    }
}
