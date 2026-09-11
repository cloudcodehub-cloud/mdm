<?php

namespace App\Services;

use App\Enums\AvailabilityRequestType;
use App\Enums\PreferredDaypart;
use App\Enums\ReviewStatus;
use App\Models\DspAvailabilityException;
use App\Models\DspAvailabilityRequest;
use App\Models\DspWeeklyAvailability;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Support\ClockMinutes;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DspAvailabilityService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    public function replaceWeekly(Employee $employee, array $days): void
    {
        $kept = [];

        foreach ($days as $day) {
            if (! isset($day['weekday'])) {
                continue;
            }

            $weekday = (int) $day['weekday'];

            if ($weekday < 0 || $weekday > 6) {
                continue;
            }

            $available = filter_var($day['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $starts = $available ? ClockMinutes::normalize((string) ($day['starts_at'] ?? '07:00')) : null;
            $ends = $available ? ClockMinutes::normalize((string) ($day['ends_at'] ?? '23:00')) : null;
            $daypart = PreferredDaypart::tryFrom((string) ($day['preferred_daypart'] ?? ''));

            $row = DspWeeklyAvailability::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'weekday' => $weekday],
                [
                    'is_available' => $available,
                    'starts_at' => $available ? $starts : null,
                    'ends_at' => $available ? $ends : null,
                    'preferred_daypart' => $daypart,
                ],
            );
            $kept[] = $row->id;
        }

        DspWeeklyAvailability::query()
            ->where('employee_id', $employee->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertException(Employee $employee, array $data): DspAvailabilityException
    {
        $available = filter_var($data['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $starts = filled($data['starts_at'] ?? null) ? ClockMinutes::normalize((string) $data['starts_at']) : null;
        $ends = filled($data['ends_at'] ?? null) ? ClockMinutes::normalize((string) $data['ends_at']) : null;

        return DspAvailabilityException::query()->create([
            'employee_id' => $employee->id,
            'exception_date' => $data['exception_date'],
            'is_available' => $available,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'note' => $this->nullableString($data['note'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitRequest(Employee $employee, User $user, array $data): DspAvailabilityRequest
    {
        $type = AvailabilityRequestType::from((string) $data['type']);

        return DspAvailabilityRequest::query()->create([
            'employee_id' => $employee->id,
            'requested_by_user_id' => $user->id,
            'type' => $type,
            'effective_on' => $data['effective_on'],
            'payload' => $data['payload'],
            'reason' => $this->nullableString($data['reason'] ?? null),
            'status' => ReviewStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return list<ScheduledVisit>
     */
    public function approveRequest(DspAvailabilityRequest $request, User $reviewer, ?string $reviewNote = null): array
    {
        $this->assertPending($request);
        $employee = $request->employee;
        $affected = [];

        if ($request->type === AvailabilityRequestType::Weekly) {
            $days = is_array($request->payload['days'] ?? null) ? $request->payload['days'] : [];
            $this->replaceWeekly($employee, $days);
            $affected = $this->flagVisitsAffectedByWeekly($employee, $request->effective_on);
        } else {
            $this->upsertException($employee, [
                'exception_date' => $request->payload['exception_date'] ?? $request->effective_on->toDateString(),
                'is_available' => $request->payload['is_available'] ?? false,
                'starts_at' => $request->payload['starts_at'] ?? null,
                'ends_at' => $request->payload['ends_at'] ?? null,
                'note' => $request->payload['note'] ?? $request->reason,
            ]);
            $date = (string) ($request->payload['exception_date'] ?? $request->effective_on->toDateString());
            $affected = $this->flagVisitsOnDate($employee, $date);
        }

        $request->forceFill([
            'status' => ReviewStatus::Approved,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->nullableString($reviewNote),
        ])->save();

        return $affected;
    }

    public function rejectRequest(DspAvailabilityRequest $request, User $reviewer, ?string $reviewNote = null): DspAvailabilityRequest
    {
        $this->assertPending($request);

        $request->forceFill([
            'status' => ReviewStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->nullableString($reviewNote),
        ])->save();

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitTimeOff(Employee $employee, User $user, array $data): EmployeeTimeOff
    {
        $status = $user->isAdmin() ? ReviewStatus::Approved : ReviewStatus::Pending;

        $record = EmployeeTimeOff::query()->create([
            'employee_id' => $employee->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'starts_at' => filled($data['starts_at'] ?? null) ? ClockMinutes::normalize((string) $data['starts_at']) : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? ClockMinutes::normalize((string) $data['ends_at']) : null,
            'reason' => $this->nullableString($data['reason'] ?? null),
            'status' => $status,
            'requested_by_user_id' => $user->id,
            'reviewed_by_user_id' => $status === ReviewStatus::Approved ? $user->id : null,
            'reviewed_at' => $status === ReviewStatus::Approved ? now() : null,
            'submitted_at' => now(),
        ]);

        if ($status === ReviewStatus::Approved) {
            $this->flagVisitsInRange($employee, (string) $data['starts_on'], (string) $data['ends_on']);
        }

        return $record;
    }

    /**
     * @return list<ScheduledVisit>
     */
    public function approveTimeOff(EmployeeTimeOff $timeOff, User $reviewer, ?string $reviewNote = null): array
    {
        if (! $timeOff->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending time-off requests can be reviewed.',
            ]);
        }

        $timeOff->forceFill([
            'status' => ReviewStatus::Approved,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->nullableString($reviewNote),
        ])->save();

        return $this->flagVisitsInRange(
            $timeOff->employee,
            $timeOff->starts_on->toDateString(),
            $timeOff->ends_on->toDateString(),
        );
    }

    public function rejectTimeOff(EmployeeTimeOff $timeOff, User $reviewer, ?string $reviewNote = null): EmployeeTimeOff
    {
        if (! $timeOff->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending time-off requests can be reviewed.',
            ]);
        }

        $timeOff->forceFill([
            'status' => ReviewStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $this->nullableString($reviewNote),
        ])->save();

        return $timeOff->refresh();
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    public function availableWindows(Employee $employee, CarbonInterface|string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $weekday = (int) $day->dayOfWeek;
        $weekly = DspWeeklyAvailability::query()
            ->where('employee_id', $employee->id)
            ->where('weekday', $weekday)
            ->first();

        $available = $this->baseWindows($weekly);

        $exceptions = DspAvailabilityException::query()
            ->where('employee_id', $employee->id)
            ->whereDate('exception_date', $day->toDateString())
            ->get();

        foreach ($exceptions as $exception) {
            $window = $this->exceptionWindow($exception);

            if ($exception->is_available) {
                $available = $this->union($available, [$window]);
            } else {
                $available = $this->subtract($available, [$window]);
            }
        }

        $previous = $day->subDay();
        $priorWeekly = DspWeeklyAvailability::query()
            ->where('employee_id', $employee->id)
            ->where('weekday', (int) $previous->dayOfWeek)
            ->first();

        if ($priorWeekly?->is_available && $priorWeekly->starts_at && $priorWeekly->ends_at) {
            $prior = ClockMinutes::window($priorWeekly->starts_at, $priorWeekly->ends_at);

            if ($prior['end'] > 1440) {
                $available = $this->union($available, [[
                    'start' => 0,
                    'end' => min(1440, $prior['end'] - 1440),
                ]]);
            }
        }

        $leave = $this->leaveWindows($employee, $day);

        return $this->clip($this->subtract($available, $leave));
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    public function leaveWindows(Employee $employee, CarbonInterface|string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $records = EmployeeTimeOff::query()
            ->approved()
            ->where('employee_id', $employee->id)
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->toDateString())
            ->get();

        $windows = [];

        foreach ($records as $record) {
            $allDay = $record->starts_on->toDateString() !== $day->toDateString()
                || $record->ends_on->toDateString() !== $day->toDateString()
                || ($record->starts_at === null && $record->ends_at === null);

            if ($allDay && $record->starts_on->toDateString() !== $record->ends_on->toDateString()) {
                $windows[] = ['start' => 0, 'end' => 1440];

                continue;
            }

            if ($record->starts_at === null && $record->ends_at === null) {
                $windows[] = ['start' => 0, 'end' => 1440];

                continue;
            }

            $start = ClockMinutes::fromTime($record->starts_at) ?? 0;
            $end = ClockMinutes::fromTime($record->ends_at) ?? 1440;

            if ($end <= $start) {
                $end += 1440;
            }

            $windows[] = ['start' => max(0, $start), 'end' => min(1440, $end)];
        }

        return $this->clip($windows);
    }

    /**
     * @return list<array{start: int, end: int, visit_id: int, client_name: string}>
     */
    public function occupiedWindows(Employee $employee, CarbonInterface|string $date, ?int $exceptVisitId = null): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $startBound = $day->copy()->subDay();
        $endBound = $day->copy()->addDay();

        $visits = ScheduledVisit::query()
            ->open()
            ->with(['client', 'shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->when($exceptVisitId !== null, fn ($query) => $query->where('id', '!=', $exceptVisitId))
            ->whereDate('service_date', '>=', $startBound->toDateString())
            ->whereDate('service_date', '<=', $endBound->toDateString())
            ->get();

        $windows = [];

        foreach ($visits as $visit) {
            $start = $visit->startsAtOn();
            $end = $visit->endsAtOn();
            $dayStart = $this->settings->at($day->toDateString(), '00:00:00');
            $dayEnd = $dayStart->addDay();

            if ($end->lte($dayStart) || $start->gte($dayEnd)) {
                continue;
            }

            $startMin = max(0, (int) $dayStart->diffInMinutes($start->gt($dayStart) ? $start : $dayStart));
            $endMin = min(1440, (int) $dayStart->diffInMinutes($end->lt($dayEnd) ? $end : $dayEnd));

            if ($endMin > $startMin) {
                $windows[] = [
                    'start' => $startMin,
                    'end' => $endMin,
                    'visit_id' => $visit->id,
                    'client_name' => $visit->client->full_name,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param  array{start: int, end: int}|null  $requested
     * @return list<array{start: int, end: int, state: string, label: string|null}>
     */
    public function timeline(Employee $employee, CarbonInterface|string $date, ?array $requested = null, ?int $exceptVisitId = null): array
    {
        $available = $this->availableWindows($employee, $date);
        $leave = $this->leaveWindows($employee, $date);
        $occupied = $this->occupiedWindows($employee, $date, $exceptVisitId);
        $marks = [0, 1440];

        foreach ([...$available, ...$leave, ...$occupied, ...($requested !== null ? [$this->clipWindow($requested)] : [])] as $window) {
            $marks[] = max(0, min(1440, $window['start']));
            $marks[] = max(0, min(1440, $window['end']));
        }

        $marks = array_values(array_unique($marks));
        sort($marks);

        $segments = [];

        for ($i = 0; $i < count($marks) - 1; $i++) {
            $start = $marks[$i];
            $end = $marks[$i + 1];

            if ($end <= $start) {
                continue;
            }

            $mid = intdiv($start + $end, 2);
            $onLeave = $this->contains($leave, $mid);
            $busy = $this->occupiedAt($occupied, $mid);
            $open = $this->contains($available, $mid);
            $confirmed = $this->availabilityConfirmed($employee, $date);
            $inRequest = $requested !== null && $mid >= $requested['start'] && $mid < min(1440, $requested['end']);
            $conflict = $inRequest && ($onLeave || $busy !== null || ! $open);

            $state = 'unavailable';
            $label = null;

            if ($onLeave) {
                $state = 'leave';
                $label = 'Leave';
            } elseif ($busy !== null) {
                $state = 'occupied';
                $label = $busy['client_name'];
            } elseif ($open && ! $confirmed) {
                $state = 'unconfirmed';
                $label = 'Availability not confirmed';
            } elseif ($open) {
                $state = 'available';
            }

            if ($inRequest && $state === 'available') {
                $state = 'requested';
                $label = 'Requested';
            }

            if ($conflict) {
                $state = 'conflict';
                $label = $label === null ? 'Conflict' : $label.' · conflict';
            }

            $last = $segments[array_key_last($segments)] ?? null;

            if ($last !== null && $last['state'] === $state && $last['label'] === $label && $last['end'] === $start) {
                $segments[array_key_last($segments)]['end'] = $end;
            } else {
                $segments[] = [
                    'start' => $start,
                    'end' => $end,
                    'state' => $state,
                    'label' => $label,
                ];
            }
        }

        return array_values($segments);
    }

    /**
     * @param  array{start: int, end: int}  $requested
     */
    public function covers(Employee $employee, CarbonInterface|string $date, array $requested): bool
    {
        $day = Carbon::parse($date)->startOfDay();
        $first = $this->clipWindow($requested);

        if ($first['end'] > $first['start'] && ! $this->windowCovered($this->availableWindows($employee, $day), $first)) {
            return false;
        }

        if ($requested['end'] > 1440) {
            $next = ['start' => 0, 'end' => min(1440, $requested['end'] - 1440)];

            if (! $this->windowCovered($this->availableWindows($employee, $day->addDay()), $next)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{start: int, end: int}  $requested
     * @return array{start: int, end: int}|null
     */
    public function overlapWithAvailability(Employee $employee, CarbonInterface|string $date, array $requested): ?array
    {
        $clip = $this->clipWindow($requested);
        $overlap = $this->intersect($this->availableWindows($employee, $date), [$clip]);

        if ($overlap === []) {
            return null;
        }

        return [
            'start' => min(array_column($overlap, 'start')),
            'end' => max(array_column($overlap, 'end')),
        ];
    }

    /**
     * @param  array{start: int, end: int}  $requested
     */
    public function hardBlockReason(Employee $employee, CarbonInterface|string $date, array $requested, ?int $exceptVisitId = null): ?string
    {
        if (! $employee->isActiveDsp()) {
            return 'Only active DSP employees can be scheduled.';
        }

        $leave = $this->leaveWindows($employee, $date);
        $clip = $this->clipWindow($requested);

        foreach ($leave as $window) {
            if ($clip['start'] < $window['end'] && $clip['end'] > $window['start']) {
                return 'This DSP has approved leave during the requested window.';
            }
        }

        if (! $this->covers($employee, $date, $requested)) {
            $hasWeekly = DspWeeklyAvailability::query()->where('employee_id', $employee->id)->exists();

            if ($hasWeekly) {
                return 'This DSP is not available during the requested window.';
            }

            if ($leave !== []) {
                return 'This DSP has approved leave during the requested window.';
            }
        }

        $occupied = $this->occupiedWindows($employee, $date, $exceptVisitId);
        $clip = $this->clipWindow($requested);

        foreach ($occupied as $window) {
            if ($clip['start'] < $window['end'] && $clip['end'] > $window['start']) {
                return 'This DSP already has a scheduled visit that overlaps this time window.';
            }
        }

        return null;
    }

    public function preferredDaypart(Employee $employee, CarbonInterface|string $date): ?PreferredDaypart
    {
        $weekday = (int) Carbon::parse($date)->dayOfWeek;
        $weekly = DspWeeklyAvailability::query()
            ->where('employee_id', $employee->id)
            ->where('weekday', $weekday)
            ->first();

        return $weekly?->preferred_daypart;
    }

    /**
     * @return list<ScheduledVisit>
     */
    public function flagVisitsAffectedByWeekly(Employee $employee, CarbonInterface|string $effectiveOn): array
    {
        $from = Carbon::parse($effectiveOn)->startOfDay();

        $visits = ScheduledVisit::query()
            ->open()
            ->with(['shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->whereDate('service_date', '>=', $from->toDateString())
            ->get();

        $flagged = [];

        foreach ($visits as $visit) {
            $template = $visit->shiftTemplate;
            $window = ClockMinutes::window(
                $visit->starts_at ?? ($template !== null ? $template->starts_at : '00:00:00'),
                $visit->ends_at ?? ($template !== null ? $template->ends_at : '00:00:00'),
            );

            if ($this->hardBlockReason($employee, $visit->service_date, $window, $visit->id) !== null) {
                $visit->forceFill([
                    'needs_attention' => true,
                    'attention_reason' => 'Approved availability change conflicts with this scheduled visit. Reassignment may be required.',
                ])->save();
                $flagged[] = $visit;
            }
        }

        return $flagged;
    }

    /**
     * @return list<ScheduledVisit>
     */
    public function flagVisitsOnDate(Employee $employee, string $date): array
    {
        return $this->flagVisitsInRange($employee, $date, $date);
    }

    /**
     * @return list<ScheduledVisit>
     */
    public function flagVisitsInRange(Employee $employee, string $from, string $to): array
    {
        $visits = ScheduledVisit::query()
            ->open()
            ->with(['shiftTemplate'])
            ->where('employee_id', $employee->id)
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->get();

        $flagged = [];

        foreach ($visits as $visit) {
            $visit->forceFill([
                'needs_attention' => true,
                'attention_reason' => 'Approved leave or availability exception overlaps this scheduled visit. Reassignment may be required.',
            ])->save();
            $flagged[] = $visit;
        }

        return $flagged;
    }

    /**
     * @return list<array{weekday: int, weekday_label: string, is_available: bool, starts_at: string|null, ends_at: string|null, preferred_daypart: string|null, configured: bool}>
     */
    public function serializeWeekly(Employee $employee): array
    {
        $labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $rows = $employee->weeklyAvailabilities->keyBy('weekday');
        $days = [];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $row = $rows->get($weekday);
            $days[] = [
                'weekday' => $weekday,
                'weekday_label' => $labels[$weekday],
                'is_available' => $row === null ? true : $row->is_available,
                'starts_at' => $row?->starts_at !== null ? substr((string) $row->starts_at, 0, 5) : ($row === null ? '07:00' : null),
                'ends_at' => $row?->ends_at !== null ? substr((string) $row->ends_at, 0, 5) : ($row === null ? '23:00' : null),
                'preferred_daypart' => $row?->preferred_daypart?->value,
                'configured' => $row !== null,
            ];
        }

        return $days;
    }

    public function availabilityConfirmed(Employee $employee, CarbonInterface|string $date): bool
    {
        $day = Carbon::parse($date)->startOfDay();

        $hasWeekly = DspWeeklyAvailability::query()
            ->where('employee_id', $employee->id)
            ->where('weekday', (int) $day->dayOfWeek)
            ->exists();

        if ($hasWeekly) {
            return true;
        }

        return DspAvailabilityException::query()
            ->where('employee_id', $employee->id)
            ->whereDate('exception_date', $day->toDateString())
            ->exists();
    }

    private function assertPending(DspAvailabilityRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending availability requests can be reviewed.',
            ]);
        }
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    private function baseWindows(?DspWeeklyAvailability $weekly): array
    {
        if ($weekly === null) {
            return [['start' => 0, 'end' => 1440]];
        }

        if (! $weekly->is_available || $weekly->starts_at === null || $weekly->ends_at === null) {
            return [];
        }

        $window = ClockMinutes::window($weekly->starts_at, $weekly->ends_at);

        if ($window['end'] > 1440) {
            return [['start' => $window['start'], 'end' => 1440]];
        }

        return [$this->clipWindow($window)];
    }

    /**
     * @return array{start: int, end: int}
     */
    private function exceptionWindow(DspAvailabilityException $exception): array
    {
        if ($exception->starts_at === null && $exception->ends_at === null) {
            return ['start' => 0, 'end' => 1440];
        }

        $start = ClockMinutes::fromTime($exception->starts_at) ?? 0;
        $end = ClockMinutes::fromTime($exception->ends_at) ?? 1440;

        if ($end <= $start) {
            $end = 1440;
        }

        return $this->clipWindow(['start' => $start, 'end' => $end]);
    }

    /**
     * @param  list<array{start: int, end: int}>  $windows
     * @param  array{start: int, end: int}  $needed
     */
    private function windowCovered(array $windows, array $needed): bool
    {
        if ($needed['end'] <= $needed['start']) {
            return true;
        }

        $cursor = $needed['start'];

        foreach ($this->clip($windows) as $window) {
            if ($window['end'] <= $cursor) {
                continue;
            }

            if ($window['start'] > $cursor) {
                return false;
            }

            $cursor = max($cursor, $window['end']);

            if ($cursor >= $needed['end']) {
                return true;
            }
        }

        return $cursor >= $needed['end'];
    }

    /**
     * @param  list<array{start: int, end: int}>  $windows
     */
    private function contains(array $windows, int $minute): bool
    {
        foreach ($windows as $window) {
            if ($minute >= $window['start'] && $minute < $window['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{start: int, end: int, visit_id: int, client_name: string}>  $occupied
     * @return array{start: int, end: int, visit_id: int, client_name: string}|null
     */
    private function occupiedAt(array $occupied, int $minute): ?array
    {
        foreach ($occupied as $window) {
            if ($minute >= $window['start'] && $minute < $window['end']) {
                return $window;
            }
        }

        return null;
    }

    /**
     * @param  list<array{start: int, end: int}>  $left
     * @param  list<array{start: int, end: int}>  $right
     * @return list<array{start: int, end: int}>
     */
    private function union(array $left, array $right): array
    {
        $all = $this->clip([...$left, ...$right]);

        if ($all === []) {
            return [];
        }

        usort($all, fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $merged = [$all[0]];

        foreach (array_slice($all, 1) as $window) {
            $last = array_key_last($merged);

            if ($window['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $window['end']);
            } else {
                $merged[] = $window;
            }
        }

        return $merged;
    }

    /**
     * @param  list<array{start: int, end: int}>  $left
     * @param  list<array{start: int, end: int}>  $right
     * @return list<array{start: int, end: int}>
     */
    private function subtract(array $left, array $right): array
    {
        $result = $this->clip($left);

        foreach ($this->clip($right) as $cut) {
            $next = [];

            foreach ($result as $window) {
                if ($cut['end'] <= $window['start'] || $cut['start'] >= $window['end']) {
                    $next[] = $window;

                    continue;
                }

                if ($cut['start'] > $window['start']) {
                    $next[] = ['start' => $window['start'], 'end' => $cut['start']];
                }

                if ($cut['end'] < $window['end']) {
                    $next[] = ['start' => $cut['end'], 'end' => $window['end']];
                }
            }

            $result = $next;
        }

        return $this->clip($result);
    }

    /**
     * @param  list<array{start: int, end: int}>  $left
     * @param  list<array{start: int, end: int}>  $right
     * @return list<array{start: int, end: int}>
     */
    private function intersect(array $left, array $right): array
    {
        $out = [];

        foreach ($left as $a) {
            foreach ($right as $b) {
                $start = max($a['start'], $b['start']);
                $end = min($a['end'], $b['end']);

                if ($end > $start) {
                    $out[] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $this->clip($out);
    }

    /**
     * @param  list<array{start: int, end: int}>  $windows
     * @return list<array{start: int, end: int}>
     */
    private function clip(array $windows): array
    {
        $clipped = [];

        foreach ($windows as $window) {
            $start = max(0, min(1440, $window['start']));
            $end = max(0, min(1440, $window['end']));

            if ($end > $start) {
                $clipped[] = ['start' => $start, 'end' => $end];
            }
        }

        usort($clipped, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $clipped;
    }

    /**
     * @param  array{start: int, end: int}  $window
     * @return array{start: int, end: int}
     */
    private function clipWindow(array $window): array
    {
        return [
            'start' => max(0, min(1440, $window['start'])),
            'end' => max(0, min(1440, $window['end'])),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
