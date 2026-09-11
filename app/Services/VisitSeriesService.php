<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Enums\SeriesEditScope;
use App\Enums\VisitRecurrencePattern;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitSeries;
use App\Models\User;
use App\Support\ClockMinutes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitSeriesService
{
    /**
     * @param  array<string, mixed>  $visitData
     * @param  array<string, mixed>  $repeat
     * @return list<ScheduledVisit>
     */
    public function createSeries(array $visitData, array $repeat): array
    {
        return DB::transaction(function () use ($visitData, $repeat): array {
            $visits = app(ScheduledVisitService::class);
            $pattern = VisitRecurrencePattern::from((string) $repeat['pattern']);
            $startsOn = Carbon::parse((string) $visitData['service_date'])->startOfDay();
            $created = [];

            $series = ScheduledVisitSeries::query()->create([
                'client_id' => $visitData['client_id'],
                'employee_id' => $visitData['employee_id'],
                'supervisor_id' => $visitData['supervisor_id'] ?? null,
                'shift_template_id' => $visitData['shift_template_id'] ?? null,
                'service_type' => $visitData['service_type'],
                'starts_at' => filled($visitData['starts_at'] ?? null) ? ClockMinutes::normalize((string) $visitData['starts_at']) : null,
                'ends_at' => filled($visitData['ends_at'] ?? null) ? ClockMinutes::normalize((string) $visitData['ends_at']) : null,
                'notes' => $visitData['notes'] ?? null,
                'pattern' => $pattern,
                'interval' => max(1, (int) ($repeat['interval'] ?? 1)),
                'days_of_week' => $repeat['days_of_week'] ?? null,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => filled($repeat['ends_on'] ?? null) ? $repeat['ends_on'] : null,
                'occurrence_count' => filled($repeat['occurrence_count'] ?? null) ? (int) $repeat['occurrence_count'] : null,
                'created_by_user_id' => $visitData['created_by_user_id'] ?? null,
            ]);

            foreach ($this->occurrenceDates($series) as $index => $date) {
                $payload = [
                    ...$visitData,
                    'service_date' => $date,
                    'series_id' => $series->id,
                    'status' => ScheduledVisitStatus::Scheduled->value,
                    'notify' => $index === 0,
                ];

                $actorId = $visitData['created_by_user_id'] ?? null;

                if (is_numeric($actorId)) {
                    $actor = User::query()->find((int) $actorId);

                    if ($actor !== null) {
                        $visits->assertSchedulable($actor, $payload);
                    }
                }

                $created[] = $visits->create($payload);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWithScope(ScheduledVisit $visit, array $data, SeriesEditScope $scope): ScheduledVisit
    {
        $visits = app(ScheduledVisitService::class);

        if ($visit->series_id === null || $scope === SeriesEditScope::This) {
            return $visits->update($visit, $data);
        }

        $targets = $this->targets($visit, $scope);

        foreach ($targets as $target) {
            if ($target->isHistorical()) {
                continue;
            }

            $payload = $data;
            $payload['service_date'] = $target->service_date->toDateString();
            $visits->update($target, $payload);
        }

        if ($visit->series !== null && $scope === SeriesEditScope::Series) {
            $visit->series->update([
                'employee_id' => $data['employee_id'] ?? $visit->series->employee_id,
                'supervisor_id' => $data['supervisor_id'] ?? $visit->series->supervisor_id,
                'shift_template_id' => $data['shift_template_id'] ?? $visit->series->shift_template_id,
                'service_type' => $data['service_type'] ?? $visit->series->service_type,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $visit->series->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $visit->series->ends_at,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $visit->series->notes,
            ]);
        }

        return $visit->fresh() ?? $visit;
    }

    public function cancelWithScope(ScheduledVisit $visit, SeriesEditScope $scope, ?string $reason, ?int $userId): void
    {
        $targets = $visit->series_id === null || $scope === SeriesEditScope::This
            ? collect([$visit])
            : $this->targets($visit, $scope);

        foreach ($targets as $target) {
            if ($target->isHistorical() || $target->status === ScheduledVisitStatus::Cancelled) {
                continue;
            }

            $target->update([
                'status' => ScheduledVisitStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
                'cancellation_reason' => $reason,
                'updated_by_user_id' => $userId,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function occurrenceDates(ScheduledVisitSeries $series): array
    {
        $dates = [];
        $cursor = $series->starts_on->toDateString();
        $limit = $series->occurrence_count ?? 12;
        $until = $series->ends_on?->toDateString();
        $guard = 0;

        while (count($dates) < $limit && $guard < 400) {
            $guard++;
            $day = Carbon::parse($cursor)->startOfDay();

            if ($until !== null && $day->toDateString() > $until) {
                break;
            }

            if ($this->matches($series, $day)) {
                $dates[] = $day->toDateString();
            }

            $cursor = $day->addDay()->toDateString();
        }

        return $dates;
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function targets(ScheduledVisit $visit, SeriesEditScope $scope)
    {
        $query = ScheduledVisit::query()
            ->with('visit')
            ->where('series_id', $visit->series_id);

        if ($scope === SeriesEditScope::Future) {
            $query->whereDate('service_date', '>=', $visit->service_date->toDateString());
        }

        return $query->orderBy('service_date')->orderBy('id')->get();
    }

    private function matches(ScheduledVisitSeries $series, Carbon $day): bool
    {
        $origin = $series->starts_on->startOfDay();
        $days = $series->days_of_week ?? [];

        return match ($series->pattern) {
            VisitRecurrencePattern::Daily => $this->intervalMatches($origin, $day, $series->interval),
            VisitRecurrencePattern::Weekdays => $day->isWeekday() && $this->intervalMatches($origin, $day, $series->interval),
            VisitRecurrencePattern::Weekly => (int) $day->dayOfWeek === (int) $origin->dayOfWeek
                && intdiv((int) $origin->diffInDays($day), 7) % $series->interval === 0,
            VisitRecurrencePattern::Biweekly => (int) $day->dayOfWeek === (int) $origin->dayOfWeek
                && intdiv((int) $origin->diffInDays($day), 7) % 2 === 0,
            VisitRecurrencePattern::Custom => in_array((int) $day->dayOfWeek, array_map('intval', $days), true)
                && intdiv((int) $origin->diffInDays($day), 7) % $series->interval === 0,
        };
    }

    private function intervalMatches(Carbon $origin, Carbon $day, int $interval): bool
    {
        if ($interval <= 1) {
            return true;
        }

        return ((int) $origin->diffInDays($day)) % $interval === 0;
    }
}
