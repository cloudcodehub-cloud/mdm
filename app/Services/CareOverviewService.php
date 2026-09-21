<?php

namespace App\Services;

use App\Enums\VisitTaskStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\User;
use App\Models\VisitTask;
use App\Support\CareServicePresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CareOverviewService
{
    public function __construct(
        private TaskRecurrenceMatcher $recurrence,
        private SettingsService $settings,
    ) {}

    /**
     * @return array{today: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, history: list<array<string, mixed>>, services: list<array{id: int, name: string, slug: string}>}
     */
    public function forClient(Client $client, User $viewer): array
    {
        $today = Carbon::parse($this->settings->today())->startOfDay();
        $client->loadMissing(['careServices' => fn ($query) => $query->active()]);
        $plan = $client->carePlans()
            ->currentlyActive()
            ->with(['taskTemplates' => fn ($query) => $query->active()->orderBy('sort_order')])
            ->orderByDesc('starts_on')
            ->first();

        $templates = $plan === null ? collect() : $plan->taskTemplates;

        $todayItems = [];
        $upcoming = [];

        foreach ($templates as $template) {
            if ($this->recurrence->appliesOn($template, $today)) {
                $todayItems[] = $this->serializeTemplate($template, $today, true);

                continue;
            }

            $next = $this->recurrence->nextAfter($template, $today);

            if ($next !== null) {
                $upcoming[] = $this->serializeTemplate($template, $next, false);
            }
        }

        usort($upcoming, function (array $left, array $right): int {
            return strcmp((string) $left['next_on'], (string) $right['next_on']);
        });

        return [
            'today' => $todayItems,
            'upcoming' => array_slice($upcoming, 0, 8),
            'history' => $this->history($client, $viewer),
            'services' => CareServicePresenter::options($client->careServices),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(CarePlanTaskTemplate $template, CarbonInterface $on, bool $isToday): array
    {
        return [
            'id' => $template->id,
            'title' => $template->title,
            'instructions' => $template->instructions,
            'recurrence' => $template->recurrence->value,
            'recurrence_label' => $template->recurrence->label(),
            'preferred_timing_label' => $template->preferred_timing?->label(),
            'is_required' => $template->is_required,
            'completable' => $isToday,
            'next_on' => $on->toDateString(),
            'next_on_label' => $isToday ? 'Today' : $on->format('D, M j'),
            'summary' => $isToday
                ? $template->recurrence->label()
                : $template->title.' — '.$on->format('l').' — '.$template->recurrence->label(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Client $client, User $viewer): array
    {
        $notes = VisitTask::query()
            ->with(['visit.employee.user', 'visit.scheduledVisit'])
            ->whereHas('visit', function ($query) use ($client, $viewer): void {
                $query->where('client_id', $client->id)->visibleTo($viewer);
            })
            ->whereIn('status', [VisitTaskStatus::Completed, VisitTaskStatus::Skipped])
            ->where(function ($query): void {
                $query->whereNotNull('completion_note')
                    ->orWhereNotNull('skip_comment');
            })
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $items = [];

        foreach ($notes as $task) {
            $visit = $task->visit;
            $employee = $visit->employee;

            if ($employee === null) {
                continue;
            }

            $user = $employee->user;
            $available = $user !== null
                && $user->id !== $viewer->id
                && $user->canMessage();

            $occurredAt = $task->completed_at ?? $task->skipped_at ?? $visit->clocked_in_at;

            if ($occurredAt === null) {
                continue;
            }

            $previousUserId = $available ? $user->id : null;
            $serviceDate = $visit->scheduledVisit?->service_date;
            $dateLabel = $serviceDate !== null
                ? $this->settings->formatDate($serviceDate)
                : 'Visit';

            $items[] = [
                'id' => $task->id,
                'task_title' => $task->title,
                'note' => $task->completion_note ?? $task->skip_comment,
                'dsp_name' => $employee->full_name,
                'previous_dsp_user_id' => $previousUserId,
                'previous_dsp_available' => $available,
                'visit_id' => $visit->id,
                'occurred_at' => $occurredAt->toIso8601String(),
                'occurred_at_label' => $this->settings->formatDateTime($occurredAt),
                'context_label' => $client->full_name.' · '.$task->title.' · '.$dateLabel.' Visit',
            ];
        }

        return $items;
    }
}
