<?php

namespace App\Services;

use App\Enums\OrganizationSettingChangeKey;
use App\Enums\ScheduledVisitStatus;
use App\Models\ScheduledVisit;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 11-01 — separate authorized migration of future Scheduled Visits
 * into a new schedule timezone context (local service-time intent preserved).
 *
 * Agency Operational Timezone changes never invoke this automatically.
 */
class ScheduleTimezoneMigrationService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{
     *     target_timezone: string,
     *     target_timezone_label: string,
     *     eligible_count: int,
     *     migratable_count: int,
     *     conflict_count: int,
     *     preserves_local_service_time_intent: bool,
     *     migratable: list<array<string, mixed>>,
     *     conflicts: list<array<string, mixed>>,
     *     note: string
     * }
     */
    public function preview(string $targetTimezone, ?CarbonInterface $now = null): array
    {
        $this->settings->assertValidTimezone($targetTimezone);
        $rows = $this->classifyEligible($targetTimezone, $now);

        /** @var list<array<string, mixed>> $migratable */
        $migratable = $rows->where('status', 'migratable')->values()->all();
        /** @var list<array<string, mixed>> $conflicts */
        $conflicts = $rows->where('status', 'conflict')->values()->all();

        return [
            'target_timezone' => $targetTimezone,
            'target_timezone_label' => $this->settings->timezoneLabel($targetTimezone),
            'eligible_count' => $rows->count(),
            'migratable_count' => count($migratable),
            'conflict_count' => count($conflicts),
            'preserves_local_service_time_intent' => true,
            'migratable' => array_slice($migratable, 0, 50),
            'conflicts' => array_slice($conflicts, 0, 50),
            'note' => 'Migration keeps wall-clock service times and recalculates underlying UTC via IANA/DST rules. Invalid or ambiguous DST local times are not auto-converted. Completed Visits, EVV, attendance, assignment history, and series exceptions are not rewritten.',
        ];
    }

    /**
     * @return array{
     *     migrated_count: int,
     *     skipped_conflict_count: int,
     *     target_timezone: string,
     *     preview: array<string, mixed>
     * }
     */
    public function migrate(
        User $actor,
        string $targetTimezone,
        string $reason,
        bool $confirmed,
        ?CarbonInterface $effectiveAt = null,
        ?CarbonInterface $now = null,
    ): array {
        $this->settings->assertValidTimezone($targetTimezone);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_schedule_migration' => 'Confirm the controlled schedule timezone migration before applying.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'schedule_migration_reason' => 'A Reason for Change is required for schedule timezone migration.',
            ]);
        }

        $preview = $this->preview($targetTimezone, $now);
        $effectiveAt = ($effectiveAt ?? $this->settings->now())->toImmutable();
        $migrated = [];

        DB::transaction(function () use ($targetTimezone, &$migrated, $now): void {
            $rows = $this->classifyEligible($targetTimezone, $now)
                ->where('status', 'migratable');

            foreach ($rows as $row) {
                /** @var ScheduledVisit $visit */
                $visit = ScheduledVisit::query()->lockForUpdate()->find($row['id']);

                if ($visit === null || $visit->status !== ScheduledVisitStatus::Scheduled) {
                    continue;
                }

                $beforeTimezone = $visit->scheduleTimezone();
                $beforeStartUtc = $visit->startsAtOn()->utc()->toIso8601String();
                $beforeEndUtc = $visit->endsAtOn()->utc()->toIso8601String();

                $visit->schedule_timezone = $targetTimezone;
                $visit->save();

                $visit->refresh();

                $migrated[] = [
                    'id' => $visit->id,
                    'service_date' => $visit->service_date->toDateString(),
                    'series_id' => $visit->series_id,
                    'previous_schedule_timezone' => $beforeTimezone,
                    'new_schedule_timezone' => $visit->scheduleTimezone(),
                    'local_starts_at' => $visit->starts_at,
                    'local_ends_at' => $visit->ends_at,
                    'previous_start_utc' => $beforeStartUtc,
                    'new_start_utc' => $visit->startsAtOn()->utc()->toIso8601String(),
                    'previous_end_utc' => $beforeEndUtc,
                    'new_end_utc' => $visit->endsAtOn()->utc()->toIso8601String(),
                ];
            }
        });

        $this->settings->recordSettingChange(
            OrganizationSettingChangeKey::ScheduleTimezoneMigration,
            sprintf('%d future schedule(s) outside %s', $preview['eligible_count'], $targetTimezone),
            sprintf('%d migrated to %s', count($migrated), $targetTimezone),
            $actor,
            $reason,
            $effectiveAt->utc(),
            [
                'confirmed' => true,
                'preserves_local_service_time_intent' => true,
                'target_timezone' => $targetTimezone,
                'migrated_count' => count($migrated),
                'skipped_conflict_count' => $preview['conflict_count'],
                'conflicts' => $preview['conflicts'],
                'migrated_sample' => array_slice($migrated, 0, 50),
                'historical_visits_evv_attendance_preserved' => true,
                'series_and_assignment_history_preserved' => true,
                'agency_timezone_not_auto_migrated' => true,
            ],
        );

        return [
            'migrated_count' => count($migrated),
            'skipped_conflict_count' => $preview['conflict_count'],
            'target_timezone' => $targetTimezone,
            'preview' => $preview,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classifyEligible(string $targetTimezone, ?CarbonInterface $now = null): Collection
    {
        $today = $this->settings->today($now);

        $visits = ScheduledVisit::query()
            ->with('shiftTemplate')
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $today)
            ->where(function ($query) use ($targetTimezone): void {
                $query->whereNull('schedule_timezone')
                    ->orWhere('schedule_timezone', '!=', $targetTimezone);
            })
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        return $visits->map(function (ScheduledVisit $visit) use ($targetTimezone): array {
            $fromTimezone = $visit->scheduleTimezone();
            $conflict = $this->dstConflictForVisit($visit, $targetTimezone);

            $beforeStart = null;
            $afterStart = null;

            try {
                $beforeStart = $visit->startsAtOn()->utc()->toIso8601String();
                if ($conflict === null) {
                    $afterStart = $this->settings
                        ->at($visit->service_date->toDateString(), $this->wallStart($visit), $targetTimezone)
                        ->utc()
                        ->toIso8601String();
                }
            } catch (\Throwable) {
                $conflict ??= 'unresolvable_schedule_window';
            }

            return [
                'id' => $visit->id,
                'service_date' => $visit->service_date->toDateString(),
                'series_id' => $visit->series_id,
                'from_timezone' => $fromTimezone,
                'to_timezone' => $targetTimezone,
                'local_starts_at' => $this->wallStart($visit),
                'local_ends_at' => $this->wallEnd($visit),
                'previous_start_utc' => $beforeStart,
                'projected_start_utc' => $afterStart,
                'status' => $conflict === null ? 'migratable' : 'conflict',
                'conflict_reason' => $conflict,
            ];
        });
    }

    private function wallStart(ScheduledVisit $visit): string
    {
        if (filled($visit->starts_at)) {
            return $this->normalizedTime((string) $visit->starts_at);
        }

        $visit->loadMissing('shiftTemplate');

        return $this->normalizedTime((string) ($visit->shiftTemplate?->starts_at ?? '00:00:00'));
    }

    private function wallEnd(ScheduledVisit $visit): string
    {
        if (filled($visit->ends_at)) {
            return $this->normalizedTime((string) $visit->ends_at);
        }

        $visit->loadMissing('shiftTemplate');

        return $this->normalizedTime((string) ($visit->shiftTemplate?->ends_at ?? '00:00:00'));
    }

    /**
     * Detect invalid (DST gap) or ambiguous (DST fold) local times in the target zone.
     * No conversion policy is invented — conflicts are reported and left untouched.
     */
    private function dstConflictForVisit(ScheduledVisit $visit, string $targetTimezone): ?string
    {
        $date = $visit->service_date->toDateString();
        $startConflict = $this->classifyLocalWallClock($date, $this->wallStart($visit), $targetTimezone);

        if ($startConflict !== null) {
            return $startConflict;
        }

        $endTime = $this->wallEnd($visit);
        $endDate = $date;

        try {
            if ($visit->spansOvernight()) {
                $endDate = Carbon::parse($date)->addDay()->toDateString();
            }
        } catch (\Throwable) {
            return 'unresolvable_schedule_window';
        }

        return $this->classifyLocalWallClock($endDate, $endTime, $targetTimezone);
    }

    private function classifyLocalWallClock(string $date, string $time, string $timezone): ?string
    {
        if (! $this->settings->isValidTimezone($timezone)) {
            return 'invalid_timezone';
        }

        $normalized = $this->normalizedTime($time);
        $tz = new DateTimeZone($timezone);
        $wall = $date.' '.$normalized;

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $wall, $tz);

        if ($parsed === false) {
            return 'invalid_local_time';
        }

        // DST gap: library adjusted away from the requested wall clock.
        if ($parsed->format('Y-m-d H:i:s') !== $wall) {
            return 'invalid_local_time_dst_gap';
        }

        if ($this->isAmbiguousLocalTime($parsed)) {
            return 'ambiguous_local_time_dst_fold';
        }

        return null;
    }

    /**
     * A local wall clock is ambiguous when the same Y-m-d H:i:s occurs twice
     * across a fall-back transition (IANA fold). No conversion side is chosen.
     */
    private function isAmbiguousLocalTime(DateTimeImmutable $local): bool
    {
        $tz = $local->getTimezone();
        $wall = $local->format('Y-m-d H:i:s');
        $dayStart = new DateTimeImmutable($local->format('Y-m-d').' 00:00:00', $tz);
        $dayEnd = new DateTimeImmutable($local->format('Y-m-d').' 23:59:59', $tz);

        $transitions = $tz->getTransitions(
            $dayStart->getTimestamp() - 7200,
            $dayEnd->getTimestamp() + 7200,
        );

        if ($transitions === false || count($transitions) < 2) {
            return false;
        }

        $ts = $local->getTimestamp();

        for ($i = 1, $count = count($transitions); $i < $count; $i++) {
            $previous = $transitions[$i - 1];
            $current = $transitions[$i];
            $delta = $previous['offset'] - $current['offset'];

            // Fall back / fold: clocks repeat for $delta seconds.
            if ($delta <= 0) {
                continue;
            }

            foreach ([$ts + $delta, $ts - $delta] as $alternateTs) {
                $altLocal = (new DateTimeImmutable('@'.$alternateTs))->setTimezone($tz);
                if ($altLocal->format('Y-m-d H:i:s') === $wall) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizedTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return substr($time, 0, 8);
    }
}
