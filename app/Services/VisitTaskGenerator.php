<?php

namespace App\Services;

use App\Enums\TaskRecurrence;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Visit;
use App\Models\VisitTask;

class VisitTaskGenerator
{
    public function __construct(
        private TaskRecurrenceMatcher $recurrence,
        private CarePlanRevisionService $revisions,
    ) {}

    public function generate(Visit $visit): void
    {
        $visit->loadMissing(['scheduledVisit.oneOffTasks', 'scheduledVisit.taskOverrides', 'carePlan']);
        $serviceDate = $visit->scheduledVisit->service_date->toDateString();

        $plan = $this->boundOrEffectivePlan($visit, $serviceDate);

        $templates = $plan === null
            ? collect()
            : CarePlanTaskTemplate::query()
                ->with('carePlan')
                ->active()
                ->where('care_plan_id', $plan->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        $overrides = $visit->scheduledVisit->taskOverrides
            ->keyBy('care_plan_task_template_id');

        foreach ($templates as $template) {
            $due = $this->recurrence->appliesOn($template, $visit->scheduledVisit->service_date);
            $override = $overrides->get($template->id);
            $include = $override !== null ? $override->included : $due;

            if (! $include) {
                continue;
            }

            VisitTask::query()->firstOrCreate(
                [
                    'visit_id' => $visit->id,
                    'care_plan_task_template_id' => $template->id,
                ],
                [
                    'title' => $template->title,
                    'instructions' => $template->instructions,
                    'recurrence' => $template->recurrence,
                    'recurrence_detail' => $template->recurrence_detail,
                    'preferred_timing' => $template->preferred_timing,
                    'is_required' => $template->is_required,
                    'note_required' => $template->note_required,
                    'can_skip' => $template->can_skip,
                    'is_critical' => $template->is_critical,
                    'sort_order' => $template->sort_order,
                    'status' => VisitTaskStatus::Pending,
                ],
            );
        }

        $this->generateOneOffs($visit);
    }

    /**
     * MDM 05 §10.6 — replacement DSP receives only unresolved/applicable work.
     * Completed/skipped tasks remain attributed to the prior Visit.
     */
    public function seedUnresolvedFromPrior(Visit $prior, Visit $next): void
    {
        $prior->loadMissing('tasks');
        $sort = 0;

        foreach ($prior->tasks as $task) {
            if ($task->status !== VisitTaskStatus::Pending) {
                continue;
            }

            $sort++;

            VisitTask::query()->create([
                'visit_id' => $next->id,
                'care_plan_task_template_id' => $task->care_plan_task_template_id,
                'scheduled_visit_one_off_task_id' => $task->scheduled_visit_one_off_task_id,
                'title' => $task->title,
                'instructions' => $task->instructions,
                'recurrence' => $task->recurrence,
                'recurrence_detail' => $task->recurrence_detail,
                'preferred_timing' => $task->preferred_timing,
                'is_required' => $task->is_required,
                'note_required' => $task->note_required,
                'can_skip' => $task->can_skip,
                'is_critical' => $task->is_critical,
                'sort_order' => $sort,
                'status' => VisitTaskStatus::Pending,
            ]);
        }
    }

    private function boundOrEffectivePlan(Visit $visit, string $serviceDate): ?CarePlan
    {
        if ($visit->care_plan_id !== null) {
            $bound = $visit->carePlan ?? CarePlan::query()->find($visit->care_plan_id);

            if ($bound !== null) {
                return $bound;
            }
        }

        return $this->revisions->effectivePlanFor($visit->client_id, $serviceDate);
    }

    private function generateOneOffs(Visit $visit): void
    {
        $visit->loadMissing('scheduledVisit.oneOffTasks');
        $sort = (int) VisitTask::query()->where('visit_id', $visit->id)->max('sort_order');

        foreach ($visit->scheduledVisit->oneOffTasks as $oneOff) {
            $sort++;

            VisitTask::query()->firstOrCreate(
                [
                    'visit_id' => $visit->id,
                    'scheduled_visit_one_off_task_id' => $oneOff->id,
                ],
                [
                    'care_plan_task_template_id' => null,
                    'title' => $oneOff->title,
                    'instructions' => $oneOff->instructions,
                    'recurrence' => TaskRecurrence::Custom,
                    'recurrence_detail' => 'Visit-specific',
                    'preferred_timing' => null,
                    'is_required' => $oneOff->is_required,
                    'note_required' => $oneOff->note_required,
                    'can_skip' => true,
                    'is_critical' => false,
                    'sort_order' => $sort,
                    'status' => VisitTaskStatus::Pending,
                ],
            );
        }
    }
}
