<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Support\DirectoryPresenter;
use App\Support\WeekBoundary;
use Illuminate\Support\Carbon;

class ScheduleCalendarService
{
    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function view(User $user, string $view, string $group, string $anchor, array $filters): array
    {
        $settings = app(SettingsService::class);
        $day = Carbon::parse($anchor !== '' ? $anchor : $settings->today())->startOfDay();
        $resolvedView = in_array($view, ['day', 'week', 'month'], true) ? $view : 'week';
        $firstDay = WeekBoundary::normalize((int) $settings->current()->first_day_of_week);

        [$start, $end] = match ($resolvedView) {
            'day' => [$day, $day->copy()],
            'month' => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()],
            default => WeekBoundary::containing($day, $firstDay),
        };

        $visits = ScheduledVisit::query()
            ->with(['client', 'employee', 'supervisor', 'shiftTemplate', 'careServices'])
            ->visibleTo($user)
            ->whereDate('service_date', '>=', $start->toDateString())
            ->whereDate('service_date', '<=', $end->toDateString())
            ->when(
                ($filters['client_id'] ?? '') !== '' && ctype_digit($filters['client_id']),
                fn ($query) => $query->where('client_id', (int) $filters['client_id']),
            )
            ->when(
                ($filters['employee_id'] ?? '') !== '' && ctype_digit($filters['employee_id']),
                fn ($query) => $query->where('employee_id', (int) $filters['employee_id']),
            )
            ->when(
                ($filters['supervisor_id'] ?? '') !== '' && ctype_digit($filters['supervisor_id']),
                fn ($query) => $query->where('supervisor_id', (int) $filters['supervisor_id']),
            )
            ->when(
                ($filters['service_type'] ?? '') !== '',
                fn ($query) => $query->where('service_type', $filters['service_type']),
            )
            ->when(
                ($filters['status'] ?? '') !== '' && ScheduledVisitStatus::tryFrom($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($visits as $visit) {
            $key = $group === 'client' ? 'c-'.$visit->client_id : 'e-'.$visit->employee_id;
            $name = $group === 'client' ? $visit->client->full_name : $visit->employee->full_name;

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'id' => $group === 'client' ? $visit->client_id : $visit->employee_id,
                    'name' => $name,
                    'visits' => [],
                ];
            }

            $rows[$key]['visits'][] = [
                ...DirectoryPresenter::scheduledVisitSummary($visit),
                'starts_minute' => $this->minute($visit, true),
                'ends_minute' => $this->minute($visit, false),
                'needs_attention' => $visit->needs_attention,
            ];
        }

        return [
            'view' => $resolvedView,
            'group' => $group === 'client' ? 'client' : 'dsp',
            'anchor' => $day->toDateString(),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => WeekBoundary::dates($start, $end),
            'first_day_of_week' => $firstDay,
            'weekday_labels' => WeekBoundary::weekdayLabels($firstDay),
            'leading_blanks' => $resolvedView === 'month'
                ? WeekBoundary::leadingBlanks($start, $firstDay)
                : 0,
            'prev_date' => WeekBoundary::shift($resolvedView, $day, -1, $firstDay)->toDateString(),
            'next_date' => WeekBoundary::shift($resolvedView, $day, 1, $firstDay)->toDateString(),
            'rows' => array_values($rows),
        ];
    }

    private function minute(ScheduledVisit $visit, bool $start): int
    {
        $template = $visit->shiftTemplate;
        $time = $start
            ? ($visit->starts_at ?? ($template !== null ? $template->starts_at : '00:00:00'))
            : ($visit->ends_at ?? ($template !== null ? $template->ends_at : '00:00:00'));

        $parts = explode(':', substr($time, 0, 8));

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }
}
