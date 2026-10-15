<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class ShiftReminderService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    /**
     * @return array{upcoming: int, ready: int, start_attention: int}
     */
    public function dispatchDue(?CarbonInterface $now = null): array
    {
        $now = $now ?? $this->settings->now();
        $counts = [
            'upcoming' => 0,
            'ready' => 0,
            'start_attention' => 0,
        ];

        foreach ($this->candidateVisits($now) as $visit) {
            $counts['upcoming'] += $this->maybeUpcoming($visit, $now) ? 1 : 0;
            $counts['ready'] += $this->maybeReady($visit, $now) ? 1 : 0;
            $counts['start_attention'] += $this->maybeStartAttention($visit, $now) ? 1 : 0;
        }

        return $counts;
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function candidateVisits(CarbonInterface $now): Collection
    {
        $from = $now->copy()->subDay()->toDateString();
        $to = $now->copy()->addDays(2)->toDateString();

        return ScheduledVisit::query()
            ->with(['employee.user', 'client', 'shiftTemplate', 'visit'])
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->orderBy('id')
            ->get();
    }

    private function maybeUpcoming(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($this->hasStartedVisit($visit) || $this->dspUser($visit) === null) {
            return false;
        }

        try {
            $start = $visit->startsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        $windowOpen = $start->copy()->subMinutes(60);
        $readyOpen = $start->copy()->subMinutes(30);

        if ($now->lt($windowOpen) || $now->gte($readyOpen)) {
            return false;
        }

        return $this->notify(
            $visit,
            'Upcoming Shift',
            $this->body($visit, 'Your shift starts in about an hour.'),
            'upcoming',
            $start,
        );
    }

    private function maybeReady(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($this->hasStartedVisit($visit) || $this->dspUser($visit) === null) {
            return false;
        }

        try {
            $start = $visit->startsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        $readyOpen = $start->copy()->subMinutes(30);

        if ($now->lt($readyOpen) || $now->gte($start)) {
            return false;
        }

        return $this->notify(
            $visit,
            'Shift Ready to Start',
            $this->body($visit, 'Start Visit is available for this shift.'),
            'ready',
            $start,
        );
    }

    /**
     * Scheduled-start reminder that transitions into Late Start attention without spam.
     */
    private function maybeStartAttention(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($this->hasStartedVisit($visit) || $this->dspUser($visit) === null) {
            return false;
        }

        try {
            $start = $visit->startsAtOn();
            $end = $visit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        if ($now->lt($start) || $now->gt($end)) {
            return false;
        }

        $late = $visit->isLateRelativeToStart($now);
        $title = $late ? 'Late Start' : 'Shift has started';
        $suffix = $late
            ? 'Scheduled start has passed and this visit has not been started.'
            : 'Your scheduled start time has arrived. Start Visit when ready.';

        return $this->notify(
            $visit,
            $title,
            $this->body($visit, $suffix),
            'start_attention',
            $start,
        );
    }

    private function notify(
        ScheduledVisit $visit,
        string $title,
        string $body,
        string $kind,
        CarbonInterface $start,
    ): bool {
        $user = $this->dspUser($visit);

        if ($user === null) {
            return false;
        }

        $sourceKey = sprintf(
            'schedule.visit.%d.reminder.%s.%s',
            $visit->id,
            $kind,
            $start->timezone($this->settings->timezone())->format('YmdHi'),
        );

        $existing = InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('source_key', $sourceKey)
            ->first();
        $previousTitle = $existing?->title;

        $this->notifications->notify(
            $user,
            InAppNotificationType::Schedule,
            $title,
            $body,
            $sourceKey,
            route('scheduled-visits.show', $visit),
        );

        return $existing === null || ($previousTitle !== null && $previousTitle !== $title);
    }

    private function body(ScheduledVisit $visit, string $suffix): string
    {
        $client = $visit->client->full_name;
        $when = $this->settings->formatDate($visit->service_date);

        try {
            $when .= ' · '.$this->settings->formatTime($visit->startsAtOn());
        } catch (RuntimeException) {
            // Keep date-only body when times cannot be resolved.
        }

        return $client.' · '.$when.'. '.$suffix;
    }

    private function hasStartedVisit(ScheduledVisit $visit): bool
    {
        if ($visit->relationLoaded('visit')) {
            return $visit->visit instanceof Visit;
        }

        return Visit::query()->where('scheduled_visit_id', $visit->id)->exists();
    }

    private function dspUser(ScheduledVisit $visit): ?User
    {
        return $visit->employee?->user;
    }
}
