<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduledVisitService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ScheduledVisit
    {
        return DB::transaction(function () use ($data): ScheduledVisit {
            $visit = ScheduledVisit::query()->create($this->persistable($data));
            $this->syncOneOffs($visit, is_array($data['one_off_tasks'] ?? null) ? $data['one_off_tasks'] : []);

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

            $visit->update($this->persistable($data));

            if ($visit->visit === null && array_key_exists('one_off_tasks', $data)) {
                $this->syncOneOffs($visit, is_array($data['one_off_tasks']) ? $data['one_off_tasks'] : []);
            }

            return $visit->fresh(['oneOffTasks']) ?? $visit;
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

        return [
            'client_id' => (int) $data['client_id'],
            'employee_id' => (int) $data['employee_id'],
            'supervisor_id' => filled($data['supervisor_id'] ?? null) ? (int) $data['supervisor_id'] : null,
            'shift_template_id' => $shiftTemplateId,
            'service_date' => $data['service_date'],
            'starts_at' => $shiftTemplateId === null ? $this->normalizedTime((string) $data['starts_at']) : null,
            'ends_at' => $shiftTemplateId === null ? $this->normalizedTime((string) $data['ends_at']) : null,
            'service_type' => $data['service_type'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ];
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
}
