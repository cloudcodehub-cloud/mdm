<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\TaskCatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarePlanSetupService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createPlan(Client $client, array $payload): CarePlan
    {
        return CarePlan::query()->create([
            'client_id' => $client->id,
            'title' => $payload['title'] ?? 'Current Care Plan',
            'starts_on' => $payload['starts_on'],
            'ends_on' => $payload['ends_on'] ?? null,
            'status' => $payload['status'] ?? CarePlanStatus::Active,
            'notes' => $payload['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    public function syncTasks(CarePlan $plan, array $tasks): CarePlan
    {
        return DB::transaction(function () use ($plan, $tasks): CarePlan {
            $keptIds = [];
            $seenCatalogIds = [];
            $sort = 0;

            foreach ($tasks as $task) {
                $rawCatalogId = $task['catalog_item_id'] ?? null;
                $catalogId = is_numeric($rawCatalogId) && (int) $rawCatalogId > 0
                    ? (int) $rawCatalogId
                    : null;

                if ($catalogId !== null) {
                    if (isset($seenCatalogIds[$catalogId])) {
                        continue;
                    }

                    $seenCatalogIds[$catalogId] = true;
                }

                $sort++;
                $attributes = $this->attributesFromPayload($plan, $task, $sort);
                $template = $this->existingTemplate($plan, $task, $catalogId);

                if ($template !== null) {
                    $template->update($attributes);
                } else {
                    $template = CarePlanTaskTemplate::query()->create($attributes);
                }

                $keptIds[] = $template->id;
            }

            CarePlanTaskTemplate::query()
                ->where('care_plan_id', $plan->id)
                ->whereNotIn('id', $keptIds === [] ? [0] : $keptIds)
                ->update(['is_active' => false]);

            return $plan->fresh(['taskTemplates']) ?? $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function attributesFromPayload(CarePlan $plan, array $task, int $sort): array
    {
        $catalog = null;
        $catalogId = isset($task['catalog_item_id']) ? (int) $task['catalog_item_id'] : 0;

        if ($catalogId > 0) {
            $catalog = TaskCatalogItem::query()->whereKey($catalogId)->first();

            if ($catalog === null) {
                throw ValidationException::withMessages([
                    'tasks' => 'A selected catalog task is no longer available.',
                ]);
            }
        }

        $title = isset($task['title']) && is_string($task['title']) && trim($task['title']) !== ''
            ? trim($task['title'])
            : $catalog?->title;

        if ($title === null) {
            throw ValidationException::withMessages([
                'tasks' => 'Each selected task needs a title.',
            ]);
        }

        if (isset($task['recurrence'])) {
            $recurrence = TaskRecurrence::from((string) $task['recurrence']);
        } elseif ($catalog !== null) {
            $recurrence = $catalog->default_recurrence;
        } else {
            $recurrence = TaskRecurrence::Daily;
        }

        $timing = null;

        if (isset($task['preferred_timing']) && is_string($task['preferred_timing']) && $task['preferred_timing'] !== '') {
            $timing = TaskPreferredTiming::from($task['preferred_timing']);
        } elseif ($catalog?->default_preferred_timing !== null && ! array_key_exists('preferred_timing', $task)) {
            $timing = $catalog->default_preferred_timing;
        }

        return [
            'care_plan_id' => $plan->id,
            'catalog_item_id' => $catalog?->id,
            'title' => $title,
            'instructions' => $this->nullableString($task['instructions'] ?? $catalog?->instructions),
            'recurrence' => $recurrence,
            'recurrence_detail' => $this->nullableString($task['recurrence_detail'] ?? $catalog?->default_recurrence_detail),
            'weekdays' => $this->weekdays($task['weekdays'] ?? $catalog?->default_weekdays),
            'interval_weeks' => isset($task['interval_weeks'])
                ? (int) $task['interval_weeks']
                : ($catalog !== null ? $catalog->default_interval_weeks : $recurrence->defaultIntervalWeeks()),
            'preferred_timing' => $timing,
            'is_required' => $this->bool($task['is_required'] ?? ($catalog !== null ? $catalog->default_is_required : true)),
            'note_required' => $this->bool($task['note_required'] ?? ($catalog !== null ? $catalog->default_note_required : false)),
            'can_skip' => $this->bool($task['can_skip'] ?? ($catalog !== null ? $catalog->default_can_skip : true)),
            'is_critical' => $this->bool($task['is_critical'] ?? ($catalog !== null ? $catalog->default_is_critical : false)),
            'sort_order' => $sort,
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function existingTemplate(CarePlan $plan, array $task, ?int $catalogId): ?CarePlanTaskTemplate
    {
        if (isset($task['id']) && is_numeric($task['id'])) {
            return CarePlanTaskTemplate::query()
                ->where('care_plan_id', $plan->id)
                ->whereKey((int) $task['id'])
                ->first();
        }

        if ($catalogId === null) {
            return null;
        }

        return CarePlanTaskTemplate::query()
            ->where('care_plan_id', $plan->id)
            ->where('catalog_item_id', $catalogId)
            ->first();
    }

    /**
     * @return list<int>|null
     */
    private function weekdays(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $days = [];

        foreach ($value as $day) {
            if (is_int($day) || (is_string($day) && ctype_digit($day))) {
                $int = (int) $day;

                if ($int >= 0 && $int <= 6) {
                    $days[] = $int;
                }
            }
        }

        return $days === [] ? null : array_values(array_unique($days));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
