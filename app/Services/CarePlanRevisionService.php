<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\ClientAdminHistoryCategory;
use App\Models\CarePlan;
use App\Models\CarePlanEffectiveDateCorrection;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarePlanRevisionService
{
    public function __construct(
        private SettingsService $settings,
        private CarePlanSetupService $setup,
        private ClientAdminHistoryService $history,
    ) {}

    /**
     * Resolve the Care Plan revision effective for a client at a given date.
     * Lazily activates any pending revision whose starts_on has been reached.
     */
    public function effectivePlanFor(Client|int $client, CarbonInterface|string|null $on = null): ?CarePlan
    {
        $clientId = $client instanceof Client ? $client->id : $client;
        $onDate = Carbon::parse($on ?? $this->settings->today())->startOfDay();

        $this->activateDuePending($clientId, $onDate);

        return CarePlan::query()
            ->where('client_id', $clientId)
            ->where('status', CarePlanStatus::Active)
            ->whereDate('starts_on', '<=', $onDate->toDateString())
            ->where(function (Builder $builder) use ($onDate): void {
                $builder->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $onDate->toDateString());
            })
            ->orderByDesc('starts_on')
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Apply a material Care Plan revision (immediate or future-effective).
     *
     * @param  array<string, mixed>  $payload
     */
    public function revise(CarePlan $source, array $payload, ?User $actor = null): CarePlan
    {
        return DB::transaction(function () use ($source, $payload, $actor): CarePlan {
            /** @var CarePlan $source */
            $source = CarePlan::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();

            if ($source->status === CarePlanStatus::Inactive) {
                throw ValidationException::withMessages([
                    'care_plan' => 'Historical Care Plans cannot be revised in place. Create a new prospective revision from the current plan.',
                ]);
            }

            $effectiveOn = $this->resolveEffectiveOn($payload);
            $today = Carbon::parse($this->settings->today())->startOfDay();

            if ($effectiveOn->lt($today)) {
                throw ValidationException::withMessages([
                    'effective_on' => 'Routine Care Plan changes may not be backdated. Use a current or future effective date.',
                ]);
            }

            $isImmediate = $effectiveOn->toDateString() === $today->toDateString();
            $reason = $this->nullableString($payload['revision_reason'] ?? $payload['reason'] ?? null);

            if ($source->status === CarePlanStatus::Pending) {
                return $this->replacePending($source, $payload, $effectiveOn, $isImmediate, $actor, $reason);
            }

            $pending = $this->pendingForClient($source->client_id);

            if ($pending !== null && ! $isImmediate) {
                // Decision 06-25: one pending future change — replace it.
                $this->cancelPending($pending, $actor, 'Replaced by a newer future-effective revision.');
            } elseif ($pending !== null && $isImmediate) {
                $this->cancelPending($pending, $actor, 'Superseded by an immediate Care Plan revision.');
            }

            $revisionNumber = (int) CarePlan::query()
                ->where('client_id', $source->client_id)
                ->max('revision_number') + 1;

            $attributes = $this->planAttributesFrom($source, $payload, $effectiveOn, $isImmediate, $actor, $reason, $revisionNumber);
            $revision = CarePlan::query()->create($attributes);

            $tasks = $this->tasksPayload($payload, $source);
            $this->setup->syncTasks($revision, $tasks);

            if ($isImmediate) {
                $this->closePlanAsOf($source, Carbon::parse($effectiveOn->toDateString())->subDay());
            } else {
                // Current plan remains authoritative until the day before the pending start.
                if ($source->ends_on === null || $source->ends_on->gte($effectiveOn)) {
                    $source->forceFill([
                        'ends_on' => Carbon::parse($effectiveOn->toDateString())->subDay()->toDateString(),
                    ])->save();
                }
            }

            $this->history->record($source->client, $actor, [
                'category' => ClientAdminHistoryCategory::CarePlan,
                'summary' => $isImmediate
                    ? 'Care Plan revision applied (immediate)'
                    : 'Care Plan revision scheduled (future-effective)',
                'old_value' => 'Revision #'.$source->revision_number,
                'new_value' => 'Revision #'.$revision->revision_number,
                'reason' => $reason,
                'effective_on' => $effectiveOn->toDateString(),
                'subject' => $revision,
            ]);

            return $revision->fresh(['taskTemplates']) ?? $revision;
        });
    }

    /**
     * Decision 06-20: Admin-only controlled correction of an incorrectly recorded effective date.
     * Does not create a content revision and does not rewrite completed Visit snapshots.
     *
     * @param  array{corrected_starts_on: string, reason: string}  $payload
     */
    public function correctEffectiveDate(CarePlan $plan, User $actor, array $payload): CarePlan
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'care_plan' => 'Only administrators may perform controlled Care Plan effective-date corrections.',
            ]);
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Provide a reason for this controlled effective-date correction.',
            ]);
        }

        $correctedStartsOn = Carbon::parse((string) $payload['corrected_starts_on'])->startOfDay();

        return DB::transaction(function () use ($plan, $actor, $reason, $correctedStartsOn): CarePlan {
            /** @var CarePlan $plan */
            $plan = CarePlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            $previousStartsOn = $plan->starts_on?->toDateString();
            $previousEffectiveAt = $plan->effective_at;

            if ($previousStartsOn === $correctedStartsOn->toDateString()) {
                return $plan;
            }

            $correctedEffectiveAt = $correctedStartsOn->copy()->startOfDay();

            CarePlanEffectiveDateCorrection::query()->create([
                'care_plan_id' => $plan->id,
                'previous_starts_on' => $previousStartsOn,
                'previous_effective_at' => $previousEffectiveAt,
                'corrected_starts_on' => $correctedStartsOn->toDateString(),
                'corrected_effective_at' => $correctedEffectiveAt,
                'reason' => $reason,
                'corrected_by_user_id' => $actor->id,
            ]);

            $plan->forceFill([
                'starts_on' => $correctedStartsOn->toDateString(),
                'effective_at' => $correctedEffectiveAt,
                'revision_reason' => trim(
                    'Effective-date correction: '.$reason
                    .($plan->revision_reason ? ' | '.$plan->revision_reason : '')
                ),
                'revised_by_user_id' => $actor->id,
            ])->save();

            $this->history->record($plan->client, $actor, [
                'category' => ClientAdminHistoryCategory::EffectiveDateCorrection,
                'summary' => 'Care Plan effective date corrected (Decision 06-20)',
                'old_value' => $previousStartsOn,
                'new_value' => $correctedStartsOn->toDateString(),
                'reason' => $reason,
                'effective_on' => $correctedStartsOn->toDateString(),
                'subject' => $plan,
                'meta' => [
                    'care_plan_id' => $plan->id,
                    'revision_number' => $plan->revision_number,
                ],
            ]);

            return $plan->refresh();
        });
    }


    /**
     * Sync tasks: mutate empty/initial or pending plans; otherwise create a prospective revision.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @param  array<string, mixed>  $options
     */
    public function syncTasksOrRevise(CarePlan $plan, array $tasks, array $options = [], ?User $actor = null): CarePlan
    {
        if ($plan->status === CarePlanStatus::Inactive) {
            throw ValidationException::withMessages([
                'care_plan' => 'Historical Care Plan tasks are immutable.',
            ]);
        }

        if ($plan->status === CarePlanStatus::Pending || ! $plan->taskTemplates()->active()->exists()) {
            if (array_key_exists('review_on', $options)) {
                $plan->forceFill([
                    'review_on' => $options['review_on'] ?: null,
                ])->save();
            }

            return $this->setup->syncTasks($plan, $tasks);
        }

        return $this->revise($plan, array_merge($options, [
            'tasks' => $tasks,
        ]), $actor);
    }

    /**
     * Clerical metadata update that does not change service meaning (Decision 06-08).
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyClericalCorrection(CarePlan $plan, array $payload): CarePlan
    {
        if ($plan->status === CarePlanStatus::Inactive) {
            throw ValidationException::withMessages([
                'care_plan' => 'Historical Care Plans cannot be edited.',
            ]);
        }

        $allowed = [];

        if (array_key_exists('title', $payload) && is_string($payload['title'])) {
            $allowed['title'] = trim($payload['title']);
        }

        if (array_key_exists('notes', $payload)) {
            $allowed['notes'] = $this->nullableString($payload['notes']);
        }

        if (array_key_exists('review_on', $payload)) {
            $allowed['review_on'] = $payload['review_on'] ?: null;
        }

        if ($allowed === []) {
            return $plan;
        }

        $plan->forceFill($allowed)->save();

        return $plan->refresh();
    }

    public function cancelPending(CarePlan $pending, ?User $actor = null, ?string $reason = null): CarePlan
    {
        if ($pending->status !== CarePlanStatus::Pending) {
            throw ValidationException::withMessages([
                'care_plan' => 'Only a not-yet-effective Care Plan revision can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($pending, $actor, $reason): CarePlan {
            /** @var CarePlan $pending */
            $pending = CarePlan::query()->whereKey($pending->id)->lockForUpdate()->firstOrFail();

            $pending->forceFill([
                'status' => CarePlanStatus::Inactive,
                'ends_on' => $pending->starts_on?->toDateString() ?? $this->settings->today(),
                'revision_reason' => $this->nullableString(
                    ($reason !== null && $reason !== '' ? $reason.' ' : '')
                    .($pending->revision_reason ?? '')
                ) ?? 'Cancelled before becoming effective.',
                'revised_by_user_id' => $actor?->id ?? $pending->revised_by_user_id,
            ])->save();

            $previous = $pending->supersedes_care_plan_id !== null
                ? CarePlan::query()->whereKey($pending->supersedes_care_plan_id)->lockForUpdate()->first()
                : null;

            if ($previous !== null && $previous->status === CarePlanStatus::Active) {
                $previous->forceFill(['ends_on' => null])->save();
            }

            $this->history->record($pending->client, $actor, [
                'category' => ClientAdminHistoryCategory::CarePlan,
                'summary' => 'Pending Care Plan revision cancelled',
                'old_value' => 'Revision #'.$pending->revision_number,
                'reason' => $reason,
                'effective_on' => $pending->starts_on?->toDateString(),
                'subject' => $pending,
            ]);

            return $pending->refresh();
        });
    }

    public function activateDuePending(int $clientId, CarbonInterface $onDate): void
    {
        DB::transaction(function () use ($clientId, $onDate): void {
            $due = CarePlan::query()
                ->where('client_id', $clientId)
                ->where('status', CarePlanStatus::Pending)
                ->whereDate('starts_on', '<=', $onDate->toDateString())
                ->orderBy('starts_on')
                ->orderBy('revision_number')
                ->lockForUpdate()
                ->get();

            foreach ($due as $pending) {
                $prior = CarePlan::query()
                    ->where('client_id', $clientId)
                    ->where('status', CarePlanStatus::Active)
                    ->whereKeyNot($pending->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($prior as $active) {
                    $this->closePlanAsOf(
                        $active,
                        Carbon::parse($pending->starts_on->toDateString())->subDay(),
                    );
                }

                $pending->forceFill([
                    'status' => CarePlanStatus::Active,
                    'effective_at' => $pending->effective_at
                        ?? Carbon::parse($pending->starts_on->toDateString())->startOfDay(),
                ])->save();
            }
        });
    }

    private function pendingForClient(int $clientId): ?CarePlan
    {
        return CarePlan::query()
            ->where('client_id', $clientId)
            ->where('status', CarePlanStatus::Pending)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replacePending(
        CarePlan $pending,
        array $payload,
        CarbonInterface $effectiveOn,
        bool $isImmediate,
        ?User $actor,
        ?string $reason,
    ): CarePlan {
        $base = $pending->supersedes_care_plan_id !== null
            ? CarePlan::query()->find($pending->supersedes_care_plan_id)
            : null;

        $this->cancelPending($pending, $actor, 'Replaced before becoming effective.');

        if ($base === null) {
            $base = $pending;
        }

        return $this->revise($base->fresh() ?? $base, array_merge($payload, [
            'effective_on' => $effectiveOn->toDateString(),
            'revision_reason' => $reason,
        ]), $actor);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function planAttributesFrom(
        CarePlan $source,
        array $payload,
        CarbonInterface $effectiveOn,
        bool $isImmediate,
        ?User $actor,
        ?string $reason,
        int $revisionNumber,
    ): array {
        $effective = Carbon::parse($effectiveOn->toDateString())->startOfDay();

        return [
            'client_id' => $source->client_id,
            'revision_number' => $revisionNumber,
            'supersedes_care_plan_id' => $source->id,
            'title' => isset($payload['title']) && is_string($payload['title']) && trim($payload['title']) !== ''
                ? trim($payload['title'])
                : $source->title,
            'starts_on' => $effective->toDateString(),
            'ends_on' => $payload['ends_on'] ?? null,
            'review_on' => array_key_exists('review_on', $payload)
                ? ($payload['review_on'] ?: null)
                : $source->review_on?->toDateString(),
            'status' => $isImmediate ? CarePlanStatus::Active : CarePlanStatus::Pending,
            'notes' => array_key_exists('notes', $payload)
                ? $this->nullableString($payload['notes'])
                : $source->notes,
            'timed_check_interval_minutes' => array_key_exists('timed_check_interval_minutes', $payload)
                ? $payload['timed_check_interval_minutes']
                : $source->timed_check_interval_minutes,
            'supervision_instruction' => array_key_exists('supervision_instruction', $payload)
                ? $this->nullableString($payload['supervision_instruction'])
                : $source->supervision_instruction,
            'revised_by_user_id' => $actor?->id,
            'revision_reason' => $reason,
            'effective_at' => $effective,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function tasksPayload(array $payload, CarePlan $source): array
    {
        if (isset($payload['tasks']) && is_array($payload['tasks'])) {
            $tasks = [];

            foreach ($payload['tasks'] as $task) {
                if (is_array($task)) {
                    // New revision must not reuse prior template IDs.
                    unset($task['id']);
                    $tasks[] = $task;
                }
            }

            return $tasks;
        }

        $source->loadMissing('taskTemplates');

        return $source->taskTemplates
            ->where('is_active', true)
            ->values()
            ->map(fn (CarePlanTaskTemplate $task): array => [
                'catalog_item_id' => $task->catalog_item_id,
                'title' => $task->title,
                'instructions' => $task->instructions,
                'recurrence' => $task->recurrence->value,
                'recurrence_detail' => $task->recurrence_detail,
                'weekdays' => $task->weekdays,
                'interval_weeks' => $task->interval_weeks,
                'preferred_timing' => $task->preferred_timing?->value,
                'is_required' => $task->is_required,
                'note_required' => $task->note_required,
                'can_skip' => $task->can_skip,
                'is_critical' => $task->is_critical,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEffectiveOn(array $payload): Carbon
    {
        $raw = $payload['effective_on'] ?? $payload['starts_on'] ?? $this->settings->today();

        return Carbon::parse((string) $raw)->startOfDay();
    }

    private function closePlanAsOf(CarePlan $plan, CarbonInterface $endsOn): void
    {
        $plan->forceFill([
            'ends_on' => Carbon::parse($endsOn->toDateString())->toDateString(),
            'status' => CarePlanStatus::Inactive,
        ])->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
