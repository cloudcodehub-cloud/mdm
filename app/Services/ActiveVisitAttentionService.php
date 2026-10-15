<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\VisitExceptionType;
use App\Models\InAppNotification;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class ActiveVisitAttentionService
{
    public function __construct(
        private SettingsService $settings,
        private VisitExceptionService $exceptions,
        private InAppNotificationService $notifications,
        private VisitSafetyCheckService $safetyChecks,
    ) {}

    /**
     * @return array{
     *     safety: array{created: int, late: int, missed: int, notified: int},
     *     wrap_up: int,
     *     past_end: int,
     *     forgotten: int,
     *     escalations: int
     * }
     */
    public function dispatchDue(?CarbonInterface $now = null): array
    {
        $now = $now ?? $this->settings->now();
        $safety = $this->safetyChecks->dispatchDue($now);
        $counts = [
            'safety' => $safety,
            'wrap_up' => 0,
            'past_end' => 0,
            'forgotten' => 0,
            'escalations' => 0,
        ];

        foreach ($this->activeVisits() as $visit) {
            $counts['wrap_up'] += $this->maybeWrapUpReminder($visit, $now) ? 1 : 0;
            $counts['past_end'] += $this->maybePastEndReminder($visit, $now) ? 1 : 0;
            $forgotten = $this->maybeForgottenReminder($visit, $now);
            $counts['forgotten'] += $forgotten ? 1 : 0;
            $counts['escalations'] += $this->maybeEscalateOpenVisit($visit, $now) ? 1 : 0;
        }

        return $counts;
    }

    public function minutesPastScheduledEnd(Visit $visit, ?CarbonInterface $now = null): ?int
    {
        $now = $now ?? $this->settings->now();

        try {
            $end = $visit->scheduledVisit->endsAtOn();
        } catch (RuntimeException) {
            return null;
        }

        if ($now->lte($end)) {
            return 0;
        }

        return (int) $end->diffInMinutes($now);
    }

    public function requiresExtendedVisitReason(Visit $visit, ?CarbonInterface $now = null): bool
    {
        $minutes = $this->minutesPastScheduledEnd($visit, $now);

        if ($minutes === null) {
            return false;
        }

        return $minutes >= (int) config('mdm.visit.overrun_reason_after_minutes', 30);
    }

    /**
     * @return Collection<int, Visit>
     */
    private function activeVisits(): Collection
    {
        return Visit::query()
            ->inProgress()
            ->with(['employee.user', 'client', 'scheduledVisit'])
            ->orderBy('id')
            ->get();
    }

    private function maybeWrapUpReminder(Visit $visit, CarbonInterface $now): bool
    {
        if (filled($visit->handover_note)) {
            return false;
        }

        try {
            $end = $visit->scheduledVisit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        // Surface wrap-up attention at scheduled end and while the visit remains open.
        if ($now->lt($end)) {
            return false;
        }

        return $this->notify(
            $visit,
            'Wrap-up note still required',
            $this->body($visit, 'Add the Visit Wrap-Up / Handover Note before clock-out.'),
            'wrap_up',
        );
    }

    private function maybePastEndReminder(Visit $visit, CarbonInterface $now): bool
    {
        try {
            $end = $visit->scheduledVisit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        if ($now->lte($end)) {
            return false;
        }

        $escalationAfter = (int) config('mdm.visit.open_escalation_after_minutes', 60);
        $minutesPast = (int) $end->diffInMinutes($now);

        // Forgotten / escalation reminder owns the stronger 60+ message.
        if ($minutesPast >= $escalationAfter) {
            return false;
        }

        return $this->notify(
            $visit,
            'Visit running past scheduled end',
            $this->body($visit, 'This Active Visit is past its scheduled end. Continue care and clock out when ready.'),
            'past_end',
        );
    }

    private function maybeForgottenReminder(Visit $visit, CarbonInterface $now): bool
    {
        try {
            $end = $visit->scheduledVisit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        $escalationAfter = (int) config('mdm.visit.open_escalation_after_minutes', 60);

        if ($now->lt($end->copy()->addMinutes($escalationAfter))) {
            return false;
        }

        return $this->notify(
            $visit,
            'Forgotten clock-out — visit still open',
            $this->body($visit, 'Complete wrap-up, final review, and clock-out. The visit stays open until you finish.'),
            'forgotten_clock_out',
        );
    }

    private function maybeEscalateOpenVisit(Visit $visit, CarbonInterface $now): bool
    {
        try {
            $end = $visit->scheduledVisit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        $escalationAfter = (int) config('mdm.visit.open_escalation_after_minutes', 60);

        if ($now->lt($end->copy()->addMinutes($escalationAfter))) {
            return false;
        }

        $existing = $visit->exceptions()
            ->where('type', VisitExceptionType::OpenVisitEscalation)
            ->whereNull('visit_task_id')
            ->whereNull('visit_safety_check_id')
            ->exists();

        if ($existing) {
            return false;
        }

        $this->exceptions->record(
            $visit,
            VisitExceptionType::OpenVisitEscalation,
            'Visit remained open 60+ minutes past scheduled end.',
            null,
            [
                'scheduled_end' => $end->toIso8601String(),
                'minutes_past_end' => (int) $end->diffInMinutes($now),
            ],
        );

        return true;
    }

    private function notify(Visit $visit, string $title, string $body, string $kind): bool
    {
        $user = $visit->employee?->user;

        if (! $user instanceof User) {
            return false;
        }

        $sourceKey = sprintf('visit.%d.attention.%s', $visit->id, $kind);

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
            route('visits.show', $visit),
        );

        return $existing === null || ($previousTitle !== null && $previousTitle !== $title);
    }

    private function body(Visit $visit, string $suffix): string
    {
        $when = $this->settings->formatDate($visit->scheduledVisit->service_date);

        try {
            $when .= ' · '.$this->settings->formatTime($visit->scheduledVisit->endsAtOn());
        } catch (RuntimeException) {
            // Keep date-only body when times cannot be resolved.
        }

        return $visit->client->full_name.' · '.$when.'. '.$suffix;
    }
}
