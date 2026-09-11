<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\Visit;
use App\Models\VisitTask;
use Illuminate\Database\Eloquent\Builder;

class VisitTaskGenerator
{
    public function __construct(private TaskRecurrenceMatcher $recurrence) {}

    public function generate(Visit $visit): void
    {
        $visit->loadMissing('scheduledVisit');
        $serviceDate = $visit->scheduledVisit->service_date->toDateString();

        $templates = CarePlanTaskTemplate::query()
            ->with('carePlan')
            ->active()
            ->whereHas('carePlan', function (Builder $query) use ($visit, $serviceDate): void {
                $query->where('client_id', $visit->client_id)
                    ->where('status', CarePlanStatus::Active)
                    ->whereDate('starts_on', '<=', $serviceDate)
                    ->where(function (Builder $builder) use ($serviceDate): void {
                        $builder->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $serviceDate);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            if (! $this->recurrence->appliesOn($template, $visit->scheduledVisit->service_date)) {
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
    }
}
