<?php

namespace App\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitAssignmentKind;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAssignment;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\ShiftTemplate;
use App\Models\User;
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
        private AuthorizationUsageService $authorizationUsage,
        private ClientServiceLocationService $serviceLocations,
        private ComplianceEligibilityService $complianceEligibility,
        private AvailabilityOverrideService $availabilityOverrides,
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
                $this->availabilityOverrides->recordIfConflict(
                    $visit,
                    $actor,
                    $this->nullableString($data['override_reason'] ?? null),
                );

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

            $visit->update($this->persistable($data, $visit));
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
            $this->recordOverrideAfterUpdate($visit, $before, $data);

            return $visit;
        });
    }

    /**
     * Validates a scheduling payload. Returns the Approved-Unavailable conflict that was
     * authorized by an override (so callers know an override record is required), or null.
     *
     * @param  array<string, mixed>  $data
     * @return array{kind: string, message: string}|null
     */
    public function assertSchedulable(User $user, array $data, ?ScheduledVisit $existing = null): ?array
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
            if (! $client->status->allowsFutureScheduling()) {
                throw ValidationException::withMessages([
                    'client_id' => 'Scheduled visits require an active client. Paused, discharged, or archived clients are not eligible for future scheduling.',
                ]);
            }

            if (! $dsp->isActiveDsp()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Only active DSP employees can be scheduled.',
                ]);
            }

            $complianceBlock = $this->complianceEligibility->serviceBlockReason($dsp);
            if ($complianceBlock !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => $complianceBlock.' New assignments are blocked until the Hard Stop is resolved or an authorized temporary exception is active.',
                ]);
            }

            $this->assertAuthorizationAllowsSchedule($client, (string) $data['service_type'], (string) $data['service_date']);
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

        if ($status !== ScheduledVisitStatus::Scheduled) {
            return null;
        }

        $requested = $this->matching->requestedWindow($data);
        $conflict = null;

        if ($requested !== null) {
            // Independent hard blocks (inactive DSP, double-booking) are never overridable.
            $block = $this->availability->hardBlockReason(
                $dsp,
                (string) $data['service_date'],
                $requested,
                $existing?->id,
                allowUnavailableOverride: true,
            );

            if ($block !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => $block,
                ]);
            }

            $conflict = $this->availability->availabilityConflict($dsp, (string) $data['service_date'], $requested);
        }

        if (! $user->isAdmin() && ! $this->matching->dspIsEligible($user, $client, $dsp)) {
            throw ValidationException::withMessages([
                'employee_id' => 'This DSP is not in the eligible pool for the client’s assigned supervisor.',
            ]);
        }

        if ($conflict !== null) {
            $this->assertOverrideAuthorized($user, $conflict, $data, $existing, $dsp);
        }

        return $conflict;
    }

    /**
     * MDM 04-19: an approved leave / approved unavailable conflict needs an authorized,
     * reasoned, Visit-specific override. Never bypasses any other hard block.
     *
     * @param  array{kind: string, message: string}  $conflict
     * @param  array<string, mixed>  $data
     */
    private function assertOverrideAuthorized(User $user, array $conflict, array $data, ?ScheduledVisit $existing, Employee $dsp): void
    {
        $reason = $this->nullableString($data['override_reason'] ?? null);

        if ($user->isDsp() || (! $user->isAdmin() && ! $user->isSupervisor())) {
            throw ValidationException::withMessages([
                'employee_id' => $conflict['message'].' Only an authorized Supervisor or Admin can override an approved unavailable period.',
            ]);
        }

        if ($reason !== null) {
            return;
        }

        if ($existing !== null && $this->isUnchangedOverriddenAssignment($existing, $dsp, $data)) {
            return;
        }

        $message = $conflict['message'].' An authorized override reason is required to assign this DSP to this visit.';

        throw ValidationException::withMessages([
            'availability_override_required' => $message,
            'override_reason' => 'Give a reason to override the DSP’s approved '.($conflict['kind'] === 'leave' ? 'leave' : 'unavailable period').' for this visit.',
            'employee_id' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isUnchangedOverriddenAssignment(ScheduledVisit $existing, Employee $dsp, array $data): bool
    {
        if ($existing->employee_id !== $dsp->id || ! $this->availabilityOverrides->hasOverrideFor($existing, $dsp->id)) {
            return false;
        }

        if ($existing->service_date->toDateString() !== Carbon::parse((string) $data['service_date'])->toDateString()) {
            return false;
        }

        $current = $this->matching->requestedWindow([
            'shift_template_id' => $existing->shift_template_id,
            'starts_at' => $existing->starts_at,
            'ends_at' => $existing->ends_at,
        ]);

        return $current !== null && $current === $this->matching->requestedWindow($data);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $data
     */
    private function recordOverrideAfterUpdate(ScheduledVisit $visit, array $before, array $data): void
    {
        $reason = $this->nullableString($data['override_reason'] ?? null);
        $actor = $this->actor($data);

        if ($reason === null || $actor === null || $visit->employee_id === null) {
            return;
        }

        $changed = (int) $before['employee_id'] !== $visit->employee_id
            || $before['service_date'] !== $visit->service_date->toDateString()
            || $before['starts_at'] !== $visit->starts_at
            || $before['ends_at'] !== $visit->ends_at
            || $before['shift_template_id'] !== $visit->shift_template_id;

        if ($changed || ! $this->availabilityOverrides->hasOverrideFor($visit, $visit->employee_id)) {
            $this->availabilityOverrides->recordIfConflict($visit, $actor, $reason);
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
    private function persistable(array $data, ?ScheduledVisit $existing = null): array
    {
        $shiftTemplateId = filled($data['shift_template_id'] ?? null) ? (int) $data['shift_template_id'] : null;

        $client = Client::query()->find((int) $data['client_id']);
        $locationSnapshot = [];
        $shouldResolveLocation = $existing === null
            || array_key_exists('client_service_location_id', $data)
            || ($existing->client_service_location_id === null && $client?->serviceLocations()->active()->exists());

        if ($shouldResolveLocation && $client !== null) {
            $locationId = filled($data['client_service_location_id'] ?? null)
                ? (int) $data['client_service_location_id']
                : ($existing?->client_service_location_id);
            $hasLocations = $client->serviceLocations()->active()->exists();

            if ($hasLocations || $locationId !== null) {
                $location = $this->serviceLocations->resolveForScheduling(
                    $client,
                    $locationId !== null ? (int) $locationId : null,
                );
                $locationSnapshot = $this->serviceLocations->snapshotAttributes($location);
            }
        }

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
            ...$locationSnapshot,
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

    public function duplicate(ScheduledVisit $source, string $serviceDate, User $actor, ?string $overrideReason = null): ScheduledVisit
    {
        $source->loadMissing(['oneOffTasks', 'careServices', 'taskOverrides']);

        $payload = [
            'client_id' => $source->client_id,
            'employee_id' => $source->employee_id,
            'supervisor_id' => $source->supervisor_id,
            'shift_template_id' => $source->shift_template_id,
            'client_service_location_id' => $source->client_service_location_id,
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
            'override_reason' => $overrideReason,
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

            ScheduledVisitAssignment::query()->create([
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

    private function assertAuthorizationAllowsSchedule(Client $client, string $serviceType, string $serviceDate): void
    {
        $serviceDate = Carbon::parse($serviceDate)->startOfDay();

        $authorizations = ClientAuthorization::query()
            ->where('client_id', $client->id)
            ->where('service_type', $serviceType)
            ->whereIn('status', [
                AuthorizationStatus::Active->value,
                AuthorizationStatus::Exhausted->value,
                AuthorizationStatus::Expired->value,
            ])
            ->whereDate('starts_on', '<=', $serviceDate->toDateString())
            ->orderByDesc('id')
            ->get();

        if ($authorizations->isEmpty()) {
            // No matching operational authorization on file — do not invent a new hard block
            // beyond configured authorization rows for this service type.
            return;
        }

        $eligible = $authorizations->first(function (ClientAuthorization $authorization) use ($serviceDate): bool {
            if ($authorization->ends_on !== null && $authorization->ends_on->lt($serviceDate)) {
                return false;
            }

            return ! $this->authorizationUsage->blocksFutureService($authorization, $serviceDate);
        });

        if ($eligible === null) {
            throw ValidationException::withMessages([
                'service_type' => 'No valid authorization remains for this service on the selected date. Expired or exhausted authorizations block future unauthorized service.',
            ]);
        }
    }
}
