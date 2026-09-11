<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitAssignmentKind;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\ClockMinutes;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduledVisitService
{
    public function __construct(
        private SchedulingMatchService $matching,
        private DspAvailabilityService $availability,
        private VisitAssignmentService $assignments,
        private SchedulingNotificationService $notifications,
    ) {}
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ScheduledVisit
    {
        return DB::transaction(function () use ($data): ScheduledVisit {
            $visit = ScheduledVisit::query()->create($this->persistable($data));
            $this->syncServices($visit, $data);
            $this->syncOneOffs($visit, is_array($data['one_off_tasks'] ?? null) ? $data['one_off_tasks'] : []);
            $this->syncTaskOverrides($visit, array_values(is_array($data['task_overrides'] ?? null) ? $data['task_overrides'] : []));

            $actor = $this->actor($data);

            if ($actor !== null) {
                $this->assignments->recordInitial($visit, $actor);

                if (($data['notify'] ?? true) !== false) {
                    $this->notifications->visitAssigned($visit);
                }
            }

            return $visit->fresh(['oneOffTasks']) ?? $visit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ScheduledVisit $visit, array $data): ScheduledVisit
    {
        return DB::transaction(function () use ($visit, $data): ScheduledVisit {
            if ($visit->status === ScheduledVisitStatus::InProgress) {
                $data['status'] = ScheduledVisitStatus::InProgress->value;
            }

            $before = [
                'employee_id' => $visit->employee_id,
                'service_date' => $visit->service_date->toDateString(),
                'starts_at' => $visit->starts_at,
                'ends_at' => $visit->ends_at,
                'shift_template_id' => $visit->shift_template_id,
                'status' => $visit->status,
            ];

            $visit->update($this->persistable($data));
            $this->syncServices($visit, $data);

            if ($visit->visit === null) {
                if (array_key_exists('one_off_tasks', $data)) {
                    $this->syncOneOffs($visit, is_array($data['one_off_tasks']) ? $data['one_off_tasks'] : []);
                }

                if (array_key_exists('task_overrides', $data)) {
                    $this->syncTaskOverrides($visit, array_values(is_array($data['task_overrides']) ? $data['task_overrides'] : []));
                }
            }

            $visit = $visit->fresh(['oneOffTasks', 'employee', 'client', 'shiftTemplate']) ?? $visit;
            $this->afterUpdate($visit, $before, $data);

            return $visit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertSchedulable(User $user, array $data, ?ScheduledVisit $existing = null): void
    {
        $client = Client::query()->find((int) $data['client_id']);
        $dsp = Employee::query()->find((int) $data['employee_id']);
        $supervisorId = $data['supervisor_id'] ?? null;
        $supervisor = filled($supervisorId) ? Employee::query()->find((int) $supervisorId) : null;
        $status = ScheduledVisitStatus::from((string) $data['status']);

        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => 'Select a valid client.',
            ]);
        }

        if ($dsp === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Select a valid DSP.',
            ]);
        }

        if ($dsp->job_type !== JobType::Dsp) {
            throw ValidationException::withMessages([
                'employee_id' => 'Only DSP employees can be scheduled.',
            ]);
        }

        if ($status === ScheduledVisitStatus::Scheduled) {
            if ($client->status !== ClientStatus::Active) {
                throw ValidationException::withMessages([
                    'client_id' => 'Scheduled visits require an active client.',
                ]);
            }

            if (! $dsp->isActiveDsp()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Only active DSP employees can be scheduled.',
                ]);
            }
        }

        if ($supervisor !== null && $supervisor->job_type !== JobType::Supervisor) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'The supervisor of record must be a supervisor employee.',
            ]);
        }

        if ($supervisor !== null && $status === ScheduledVisitStatus::Scheduled && ! $supervisor->isActiveSupervisor()) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'The supervisor of record must be an active supervisor.',
            ]);
        }

        if (! $this->userMaySchedulePair($user, $client, $dsp)) {
            throw ValidationException::withMessages([
                'client_id' => 'You can only schedule visits for clients and DSPs in your scope.',
            ]);
        }

        $shiftTemplateId = $data['shift_template_id'] ?? null;
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;

        if (filled($shiftTemplateId) && (filled($startsAt) || filled($endsAt))) {
            throw ValidationException::withMessages([
                'shift_template_id' => 'Choose a shift template or custom times, not both.',
            ]);
        }

        if (blank($shiftTemplateId) && (blank($startsAt) || blank($endsAt))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Provide a shift template or both start and end times.',
            ]);
        }

        if (blank($shiftTemplateId) && filled($startsAt) && filled($endsAt) && $this->normalizedTime((string) $startsAt) === $this->normalizedTime((string) $endsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Custom start and end times cannot be the same. Overnight windows must end at a later clock time on the next day.',
            ]);
        }

        $this->assertNoOverlap($data, $existing);

        if ($status === ScheduledVisitStatus::Scheduled) {
            $requested = $this->matching->requestedWindow($data);

            if ($requested !== null) {
                $block = $this->availability->hardBlockReason(
                    $dsp,
                    (string) $data['service_date'],
                    $requested,
                    $existing?->id,
                );

                if ($block !== null) {
                    throw ValidationException::withMessages([
                        'employee_id' => $block,
                    ]);
                }
            }

            if (! $user->isAdmin() && ! $this->matching->dspIsEligible($user, $client, $dsp)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This DSP is not in the eligible pool for the client’s assigned supervisor.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertNoOverlap(array $data, ?ScheduledVisit $existing = null): void
    {
        $status = ScheduledVisitStatus::from((string) $data['status']);

        if ($status !== ScheduledVisitStatus::Scheduled) {
            return;
        }

        $candidate = $this->windowFromPayload($data);
        $serviceDate = Carbon::parse((string) $data['service_date'])->startOfDay();

        $others = ScheduledVisit::query()
            ->open()
            ->with('shiftTemplate')
            ->where('employee_id', (int) $data['employee_id'])
            ->when($existing !== null, fn ($query) => $query->where('id', '!=', $existing->id))
            ->whereDate('service_date', '>=', $serviceDate->subDay()->toDateString())
            ->whereDate('service_date', '<=', Carbon::parse((string) $data['service_date'])->startOfDay()->addDay()->toDateString())
            ->get();

        foreach ($others as $other) {
            if ($candidate['start']->lt($other->endsAtOn()) && $candidate['end']->gt($other->startsAtOn())) {
                throw ValidationException::withMessages([
                    'service_date' => 'This DSP already has a scheduled visit that overlaps this time window.',
                ]);
            }
        }
    }

    public function userMaySchedulePair(User $user, Client $client, Employee $dsp): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isSupervisor() || $user->employee === null) {
            return false;
        }

        if (! $user->can('view', $client)) {
            return false;
        }

        if ($user->can('view', $dsp)) {
            return true;
        }

        return $dsp->clientAssignments()
            ->active()
            ->where('client_id', $client->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistable(array $data): array
    {
        $shiftTemplateId = filled($data['shift_template_id'] ?? null) ? (int) $data['shift_template_id'] : null;

        $payload = [
            'client_id' => (int) $data['client_id'],
            'employee_id' => (int) $data['employee_id'],
            'supervisor_id' => filled($data['supervisor_id'] ?? null) ? (int) $data['supervisor_id'] : null,
            'shift_template_id' => $shiftTemplateId,
            'service_date' => $data['service_date'],
            'starts_at' => $shiftTemplateId === null ? $this->normalizedTime((string) $data['starts_at']) : null,
            'ends_at' => $shiftTemplateId === null ? $this->normalizedTime((string) $data['ends_at']) : null,
            'service_type' => $this->displayServiceType($data),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ];

        if (array_key_exists('series_id', $data)) {
            $payload['series_id'] = filled($data['series_id']) ? (int) $data['series_id'] : null;
        }

        if (filled($data['created_by_user_id'] ?? null)) {
            $payload['created_by_user_id'] = (int) $data['created_by_user_id'];
        }

        if (filled($data['updated_by_user_id'] ?? null)) {
            $payload['updated_by_user_id'] = (int) $data['updated_by_user_id'];
        }

        if (array_key_exists('cancellation_reason', $data)) {
            $payload['cancellation_reason'] = $data['cancellation_reason'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function displayServiceType(array $data): string
    {
        $ids = $this->serviceIdsFrom($data);

        if ($ids !== []) {
            $names = CareService::query()
                ->whereIn('id', $ids)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->all();

            if ($names !== []) {
                return implode(' · ', $names);
            }
        }

        return (string) ($data['service_type'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function serviceIdsFrom(array $data): array
    {
        $raw = $data['service_ids'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncServices(ScheduledVisit $visit, array $data): void
    {
        if (! array_key_exists('service_ids', $data)) {
            return;
        }

        $ids = $this->serviceIdsFrom($data);
        $sync = [];

        foreach ($ids as $index => $id) {
            $sync[$id] = ['sort_order' => $index + 1];
        }

        $visit->careServices()->sync($sync);
    }

    /**
     * @param  list<mixed>  $rows
     */
    private function syncTaskOverrides(ScheduledVisit $visit, array $rows): void
    {
        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $templateId = isset($row['care_plan_task_template_id']) && is_numeric($row['care_plan_task_template_id'])
                ? (int) $row['care_plan_task_template_id']
                : 0;

            if ($templateId < 1) {
                continue;
            }

            $template = CarePlanTaskTemplate::query()->find($templateId);

            if ($template === null) {
                continue;
            }

            $included = filter_var($row['included'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $reason = $this->nullableString($row['exclusion_reason'] ?? null);

            $override = ScheduledVisitTaskOverride::query()->updateOrCreate(
                [
                    'scheduled_visit_id' => $visit->id,
                    'care_plan_task_template_id' => $templateId,
                ],
                [
                    'included' => $included,
                    'exclusion_reason' => $included ? null : $reason,
                ],
            );
            $kept[] = $override->id;
        }

        ScheduledVisitTaskOverride::query()
            ->where('scheduled_visit_id', $visit->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    private function windowFromPayload(array $data): array
    {
        $visit = new ScheduledVisit([
            'shift_template_id' => filled($data['shift_template_id'] ?? null) ? $data['shift_template_id'] : null,
            'service_date' => $data['service_date'],
            'starts_at' => filled($data['starts_at'] ?? null) ? $this->normalizedTime((string) $data['starts_at']) : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? $this->normalizedTime((string) $data['ends_at']) : null,
        ]);

        if ($visit->shift_template_id !== null) {
            $template = ShiftTemplate::query()->find($visit->shift_template_id);

            if ($template !== null) {
                $visit->setRelation('shiftTemplate', $template);
            }
        }

        return [
            'start' => $visit->startsAtOn(),
            'end' => $visit->endsAtOn(),
        ];
    }

    /**
     * @param  array<int, mixed>  $tasks
     */
    private function syncOneOffs(ScheduledVisit $visit, array $tasks): void
    {
        $kept = [];
        $sort = 0;

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $title = isset($task['title']) && is_string($task['title']) ? trim($task['title']) : '';

            if ($title === '') {
                continue;
            }

            $sort++;
            $attributes = [
                    'title' => $title,
                    'catalog_item_id' => isset($task['catalog_item_id']) && is_numeric($task['catalog_item_id'])
                        ? (int) $task['catalog_item_id']
                        : null,
                    'instructions' => $this->nullableString($task['instructions'] ?? null),
                    'note_required' => filter_var($task['note_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_required' => filter_var($task['is_required'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $sort,
            ];

            $existing = null;

            if (isset($task['id']) && is_numeric($task['id'])) {
                $existing = ScheduledVisitOneOffTask::query()
                    ->where('scheduled_visit_id', $visit->id)
                    ->whereKey((int) $task['id'])
                    ->first();
            }

            if ($existing !== null) {
                $existing->update($attributes);
                $kept[] = $existing->id;
            } else {
                $created = ScheduledVisitOneOffTask::query()->create([
                    'scheduled_visit_id' => $visit->id,
                    ...$attributes,
                ]);
                $kept[] = $created->id;
            }
        }

        ScheduledVisitOneOffTask::query()
            ->where('scheduled_visit_id', $visit->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->when($kept === [], fn ($query) => $query)
            ->delete();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizedTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return substr($time, 0, 8);
    }

    public function duplicate(ScheduledVisit $source, string $serviceDate, User $actor): ScheduledVisit
    {
        $source->loadMissing(['oneOffTasks', 'careServices', 'taskOverrides']);

        $payload = [
            'client_id' => $source->client_id,
            'employee_id' => $source->employee_id,
            'supervisor_id' => $source->supervisor_id,
            'shift_template_id' => $source->shift_template_id,
            'service_date' => $serviceDate,
            'starts_at' => $source->starts_at,
            'ends_at' => $source->ends_at,
            'service_type' => $source->service_type,
            'status' => ScheduledVisitStatus::Scheduled->value,
            'notes' => $source->notes,
            'one_off_tasks' => $source->oneOffTasks->map(fn ($task): array => [
                'title' => $task->title,
                'catalog_item_id' => $task->catalog_item_id,
                'instructions' => $task->instructions,
                'note_required' => $task->note_required,
                'is_required' => $task->is_required,
            ])->all(),
            'service_ids' => $source->careServices->pluck('id')->all(),
            'task_overrides' => $source->taskOverrides->map(fn ($row): array => [
                'care_plan_task_template_id' => $row->care_plan_task_template_id,
                'included' => $row->included,
                'exclusion_reason' => $row->exclusion_reason,
            ])->all(),
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ];

        $this->assertSchedulable($actor, $payload);

        return $this->create($payload);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $data
     */
    private function afterUpdate(ScheduledVisit $visit, array $before, array $data): void
    {
        $actor = $this->actor($data);

        if ((int) $before['employee_id'] !== $visit->employee_id && $actor !== null) {
            $previous = Employee::query()->find((int) $before['employee_id']);
            $current = $visit->assignments()->whereNull('ended_at')->first();

            if ($current !== null) {
                $current->forceFill(['ended_at' => now()])->save();
            }

            \App\Models\ScheduledVisitAssignment::query()->create([
                'scheduled_visit_id' => $visit->id,
                'employee_id' => $visit->employee_id,
                'assigned_by_user_id' => $actor->id,
                'kind' => VisitAssignmentKind::Reassigned,
                'reason' => (string) ($data['replacement_reason'] ?? 'DSP changed on edit'),
                'assigned_at' => now(),
            ]);

            if ($previous !== null) {
                $this->notifications->visitReassigned($visit, $previous);
            }
        }

        if ($visit->status === ScheduledVisitStatus::Cancelled && $before['status'] !== ScheduledVisitStatus::Cancelled) {
            $visit->forceFill([
                'cancelled_at' => $visit->cancelled_at ?? now(),
                'cancelled_by_user_id' => $data['cancelled_by_user_id'] ?? $data['updated_by_user_id'] ?? null,
            ])->save();
            $this->notifications->visitCancelled($visit);
        }

        $timeChanged = $before['service_date'] !== $visit->service_date->toDateString()
            || $before['starts_at'] !== $visit->starts_at
            || $before['ends_at'] !== $visit->ends_at
            || $before['shift_template_id'] !== $visit->shift_template_id;

        if ($timeChanged && $visit->status === ScheduledVisitStatus::Scheduled) {
            $this->notifications->visitTimeChanged($visit);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function actor(array $data): ?User
    {
        $id = $data['created_by_user_id'] ?? $data['updated_by_user_id'] ?? null;

        if (! is_numeric($id)) {
            return null;
        }

        return User::query()->find((int) $id);
    }
}
