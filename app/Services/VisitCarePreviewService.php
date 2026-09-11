<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use Illuminate\Support\Carbon;

class VisitCarePreviewService
{
    public function __construct(private TaskRecurrenceMatcher $recurrence) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forClientOnDate(Client $client, string $serviceDate, ?ScheduledVisit $scheduledVisit = null): array
    {
        $day = Carbon::parse($serviceDate)->startOfDay();
        $plan = $client->carePlans()
            ->where('status', CarePlanStatus::Active)
            ->with(['taskTemplates' => fn ($query) => $query->active()->orderBy('sort_order')])
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $day->toDateString());
            })
            ->orderByDesc('starts_on')
            ->first();

        $items = [];

        if ($plan !== null) {
            foreach ($plan->taskTemplates as $template) {
                $due = $this->recurrence->appliesOn($template, $day);
                $items[] = $this->serializeTemplate($template, $due);
            }
        }

        $oneOffs = $scheduledVisit === null
            ? collect()
            : $scheduledVisit->oneOffTasks;

        foreach ($oneOffs as $task) {
            $items[] = $this->serializeOneOff($task);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(CarePlanTaskTemplate $template, bool $due): array
    {
        return [
            'key' => 'plan-'.$template->id,
            'title' => $template->title,
            'recurrence' => $template->recurrence->value,
            'recurrence_label' => $template->recurrence->label(),
            'due' => $due,
            'due_label' => $due ? 'Due' : 'Not due',
            'source' => 'care_plan',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOneOff(ScheduledVisitOneOffTask $task): array
    {
        return [
            'key' => 'one-off-'.$task->id,
            'title' => $task->title,
            'recurrence' => 'custom',
            'recurrence_label' => 'Visit-specific',
            'due' => true,
            'due_label' => 'Due',
            'source' => 'one_off',
        ];
    }
}
