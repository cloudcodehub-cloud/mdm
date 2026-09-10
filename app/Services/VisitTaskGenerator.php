<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\Visit;
use App\Models\VisitTask;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VisitTaskGenerator
{
    public function generate(Visit $visit): void
    {
        $visit->loadMissing('scheduledVisit');
        $serviceDate = $visit->scheduledVisit->service_date->toDateString();

        $templates = CarePlanTaskTemplate::query()
            ->with('carePlan')
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
            if (! $this->appliesOn($template, $visit->scheduledVisit->service_date)) {
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
                    'is_required' => $template->is_required,
                    'sort_order' => $template->sort_order,
                    'status' => VisitTaskStatus::Pending,
                ],
            );
        }
    }

    private function appliesOn(CarePlanTaskTemplate $template, CarbonInterface $serviceDate): bool
    {
        $anchor = Carbon::parse($template->carePlan->starts_on)->startOfDay();
        $day = Carbon::parse($serviceDate)->startOfDay();

        return match ($template->recurrence) {
            TaskRecurrence::Daily, TaskRecurrence::Custom => true,
            TaskRecurrence::Weekly => $anchor->dayOfWeek === $day->dayOfWeek,
            TaskRecurrence::Biweekly => $anchor->dayOfWeek === $day->dayOfWeek
                && ((int) $anchor->diffInDays($day)) % 14 === 0,
            TaskRecurrence::Monthly => $this->monthlyApplies($anchor, $day),
            TaskRecurrence::Annual => $anchor->month === $day->month && $this->monthlyApplies($anchor, $day),
        };
    }

    private function monthlyApplies(CarbonInterface $anchor, CarbonInterface $day): bool
    {
        if ($anchor->day === $day->day) {
            return true;
        }

        return $anchor->day > $day->daysInMonth && $day->isLastOfMonth();
    }
}
