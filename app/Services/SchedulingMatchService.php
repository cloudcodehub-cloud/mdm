<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\PreferredDaypart;
use App\Enums\ScheduledVisitStatus;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\ClockMinutes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SchedulingMatchService
{
    public function __construct(
        private DspAvailabilityService $availability,
        private SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function board(User $user, array $data, ?ScheduledVisit $existing = null): array
    {
        $client = Client::query()->with('supervisor')->find((int) $data['client_id']);
        $serviceDate = (string) $data['service_date'];
        $requested = $this->requestedWindow($data);
        $serviceType = (string) ($data['service_type'] ?? '');

        if ($client === null || $serviceDate === '' || $requested === null) {
            return [
                'dsps' => [],
                'coverage' => null,
                'authorization' => null,
                'supervisor' => null,
            ];
        }

        $pool = $this->eligibleDsps($user, $client);
        $rows = [];

        foreach ($pool as $dsp) {
            $rows[] = $this->dspRow($dsp, $client, $serviceDate, $requested, $existing?->id);
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['hard_blocked'] !== $b['hard_blocked']) {
                return $a['hard_blocked'] <=> $b['hard_blocked'];
            }

            return $b['score'] <=> $a['score'];
        });

        $full = array_values(array_filter($rows, fn (array $row): bool => ! $row['hard_blocked'] && $row['fully_available']));
        $coverage = null;

        if ($full === []) {
            $coverage = $this->coverageGaps($rows, $requested, $serviceDate);
        }

        return [
            'supervisor' => $client->supervisor === null ? null : [
                'id' => $client->supervisor->id,
                'name' => $client->supervisor->full_name,
                'source' => 'client_profile',
            ],
            'requested' => [
                'start' => $requested['start'],
                'end' => $requested['end'],
                'label' => ClockMinutes::toLabel($requested['start']).'–'.ClockMinutes::toLabel($requested['end']),
            ],
            'dsps' => $rows,
            'coverage' => $coverage,
            'authorization' => $this->authorizationWarning($client, $serviceType, $serviceDate),
        ];
    }

    /**
     * @return Collection<int, Employee>
     */
    public function eligibleDsps(User $user, Client $client): Collection
    {
        $query = Employee::query()
            ->with(['clientAssignments' => fn ($q) => $q->active()])
            ->where('job_type', JobType::Dsp)
            ->where('employment_status', EmploymentStatus::Active);

        if (! $user->isAdmin()) {
            $query->where(function ($builder) use ($user): void {
                $builder->visibleTo($user)
                    ->orWhereIn('id', ClientDspAssignment::query()
                        ->active()
                        ->whereIn('client_id', Client::query()->visibleTo($user)->select('id'))
                        ->select('employee_id'));
            });
        }

        $dsps = $query->orderBy('last_name')->orderBy('first_name')->get();

        return $dsps->filter(function (Employee $dsp) use ($client, $user): bool {
            if ($user->isAdmin()) {
                return $dsp->supervisor_id === $client->supervisor_id
                    || $this->assignedToClient($dsp, $client);
            }

            return $dsp->supervisor_id === $client->supervisor_id
                || $this->assignedToClient($dsp, $client);
        })->values();
    }

    public function dspIsEligible(User $user, Client $client, Employee $dsp): bool
    {
        if (! $dsp->isActiveDsp()) {
            return false;
        }

        return $this->eligibleDsps($user, $client)->contains(fn (Employee $item): bool => $item->id === $dsp->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{start: int, end: int}|null
     */
    public function requestedWindow(array $data): ?array
    {
        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;

        if (filled($data['shift_template_id'] ?? null)) {
            $template = ShiftTemplate::query()->find((int) $data['shift_template_id']);

            if ($template === null) {
                return null;
            }

            return ClockMinutes::window($template->starts_at, $template->ends_at);
        }

        if (! is_string($starts) || ! is_string($ends) || trim($starts) === '' || trim($ends) === '') {
            return null;
        }

        return ClockMinutes::window($starts, $ends);
    }

    /**
     * @param  array{start: int, end: int}  $requested
     * @return array<string, mixed>
     */
    public function dspRow(Employee $dsp, Client $client, string $serviceDate, array $requested, ?int $exceptVisitId = null): array
    {
        $assigned = $this->assignedToClient($dsp, $client);
        $historyCount = ScheduledVisit::query()
            ->where('employee_id', $dsp->id)
            ->where('client_id', $client->id)
            ->where('status', '!=', ScheduledVisitStatus::Cancelled)
            ->count();
        $workload = $this->workload($dsp, $serviceDate);
        $block = $this->availability->hardBlockReason($dsp, $serviceDate, $requested, $exceptVisitId);
        $covers = $block === null && $this->availability->covers($dsp, $serviceDate, $requested);
        $confirmed = $this->availability->availabilityConfirmed($dsp, $serviceDate);
        $overlap = $this->availability->overlapWithAvailability($dsp, $serviceDate, $requested);
        $daypart = $this->availability->preferredDaypart($dsp, $serviceDate);
        $requestedDaypart = $this->daypartFor($requested['start']);

        $reasons = [];
        $warnings = [];
        $score = 0;

        if ($assigned) {
            $score += 100;
            $reasons[] = 'Assigned to client';
        } else {
            $warnings[] = 'Not a currently assigned DSP for this client';
            $score -= 15;
        }

        if ($covers) {
            $score += 80;
            $reasons[] = $confirmed ? 'Available' : 'Availability not confirmed';
        } elseif ($overlap !== null) {
            $score += 20;
            $reasons[] = 'Partial: '.ClockMinutes::toLabel($overlap['start']).'–'.ClockMinutes::toLabel($overlap['end']);
        }

        if ($covers && ! $confirmed) {
            $warnings[] = 'Availability not confirmed — weekly hours are not set for this day.';
        }

        if ($historyCount > 0) {
            $score += min(40, $historyCount * 8);
            $reasons[] = 'Prior history with client';
        } elseif ($assigned === false) {
            $warnings[] = 'Continuity-of-care concern: no prior visits with this client';
        }

        $score += max(0, 40 - (int) round($workload['week_hours']));
        $reasons[] = $this->hoursLabel($workload['week_hours']).' this week';

        if ($workload['day_hours'] >= 8) {
            $warnings[] = 'Unusually heavy day';
            $score -= 10;
        }

        if ($workload['week_hours'] >= 40) {
            $warnings[] = 'High weekly workload';
            $score -= 12;
        }

        if ($daypart !== null && $daypart === $requestedDaypart) {
            $score += 8;
            $reasons[] = 'Preferred '.$daypart->value;
        }

        if ($block !== null) {
            $score -= 200;
        }

        $capacity = min(100, (int) round(($workload['week_hours'] / 40) * 100));

        return [
            'id' => $dsp->id,
            'name' => $dsp->full_name,
            'employee_number' => $dsp->employee_number,
            'photo_url' => app(ProfilePhotoService::class)->employeeUrl($dsp),
            'initials' => $dsp->initials(),
            'assigned_to_client' => $assigned,
            'fully_available' => $covers,
            'availability_confirmed' => $confirmed,
            'hard_blocked' => $block !== null,
            'block_reason' => $block,
            'score' => $score,
            'reasons' => $reasons,
            'reason_label' => implode(' · ', $reasons),
            'warnings' => $warnings,
            'workload' => $workload,
            'capacity_percent' => $capacity,
            'preferred_daypart' => $daypart?->value,
            'partial' => $overlap,
            'timeline' => $this->availability->timeline($dsp, $serviceDate, $requested, $exceptVisitId),
            'history_count' => $historyCount,
        ];
    }

    /**
     * @return array{day_hours: float, week_hours: float, visit_count: int}
     */
    public function workload(Employee $dsp, string $serviceDate): array
    {
        $day = Carbon::parse($serviceDate)->startOfDay();
        $firstDay = $this->settings->current()->first_day_of_week;
        $weekStart = $day->copy()->startOfWeek($firstDay === 1 ? Carbon::MONDAY : Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $visits = ScheduledVisit::query()
            ->open()
            ->with('shiftTemplate')
            ->where('employee_id', $dsp->id)
            ->whereDate('service_date', '>=', $weekStart->toDateString())
            ->whereDate('service_date', '<=', $weekEnd->toDateString())
            ->get();

        $dayMinutes = 0;
        $weekMinutes = 0;
        $dayCount = 0;

        foreach ($visits as $visit) {
            $minutes = $visit->durationMinutes();
            $weekMinutes += $minutes;

            if ($visit->service_date->toDateString() === $day->toDateString()) {
                $dayMinutes += $minutes;
                $dayCount++;
            }
        }

        return [
            'day_hours' => round($dayMinutes / 60, 1),
            'week_hours' => round($weekMinutes / 60, 1),
            'visit_count' => $dayCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{start: int, end: int}  $requested
     * @return array<string, mixed>
     */
    private function coverageGaps(array $rows, array $requested, string $serviceDate): array
    {
        $options = [];

        foreach ($rows as $row) {
            if ($row['hard_blocked'] && ($row['block_reason'] ?? '') !== 'This DSP is not available during the requested window.') {
                continue;
            }

            if (is_array($row['partial'] ?? null)) {
                $options[] = [
                    'employee_id' => $row['id'],
                    'name' => $row['name'],
                    'label' => $row['name'].': available '.ClockMinutes::toLabel($row['partial']['start']).'–'.ClockMinutes::toLabel($row['partial']['end']),
                ];
            }
        }

        $nextDay = Carbon::parse($serviceDate)->addDay()->toDateString();

        foreach ($rows as $row) {
            if ($row['hard_blocked']) {
                continue;
            }

            $dsp = Employee::query()->whereKey((int) $row['id'])->first();

            if ($dsp === null) {
                continue;
            }

            if ($this->availability->covers($dsp, $nextDay, $requested)) {
                $options[] = [
                    'employee_id' => $dsp->id,
                    'name' => $dsp->full_name,
                    'label' => $dsp->full_name.': available full shift next day',
                ];
            }
        }

        $unique = [];

        foreach ($options as $option) {
            $unique[$option['label']] = $option;
        }

        return [
            'message' => 'No DSP is fully available '.$this->windowLabel($requested).'.',
            'options' => array_values($unique),
        ];
    }

    /**
     * @return array{level: string, message: string, items: list<array{level: string, service: string, message: string}>}|null
     */
    public function authorizationWarning(Client $client, string $serviceType, string $serviceDate): ?array
    {
        if ($serviceType === '') {
            return null;
        }

        $names = preg_split('/\s*[·,;|]\s*/u', $serviceType) ?: [$serviceType];
        $items = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $warning = $this->authorizationWarningForName($client, $name, $serviceDate);

            if ($warning !== null) {
                $items[$name] = $warning;
            }
        }

        if ($items === []) {
            return null;
        }

        $list = array_values($items);

        return [
            'level' => 'warning',
            'message' => implode(' ', array_column($list, 'message')),
            'items' => $list,
        ];
    }

    /**
     * @return array{level: string, service: string, message: string}|null
     */
    private function authorizationWarningForName(Client $client, string $serviceType, string $serviceDate): ?array
    {
        $day = Carbon::parse($serviceDate)->startOfDay();
        $dateLabel = $day->format('M j');
        $message = $serviceType.' — no active authorization for '.$dateLabel.'.';
        $matches = $client->authorizations()
            ->whereRaw('lower(service_type) = ?', [mb_strtolower($serviceType)])
            ->get();

        if ($matches->isEmpty()) {
            return [
                'level' => 'warning',
                'service' => $serviceType,
                'message' => $message,
            ];
        }

        $valid = $matches->first(fn ($auth): bool => $auth->isCurrentlyActive($day));

        if ($valid !== null) {
            return null;
        }

        return [
            'level' => 'warning',
            'service' => $serviceType,
            'message' => $message,
        ];
    }

    public function assignedToClient(Employee $dsp, Client $client): bool
    {
        foreach ($dsp->clientAssignments as $assignment) {
            if ($assignment->isActive() && $assignment->client_id === $client->id) {
                return true;
            }
        }

        return $dsp->clientAssignments()
            ->active()
            ->where('client_id', $client->id)
            ->exists();
    }

    private function daypartFor(int $startMinute): PreferredDaypart
    {
        $hour = intdiv($startMinute % 1440, 60);

        return match (true) {
            $hour >= 22 || $hour < 6 => PreferredDaypart::Overnight,
            $hour >= 17 => PreferredDaypart::Evening,
            $hour >= 12 => PreferredDaypart::Afternoon,
            default => PreferredDaypart::Morning,
        };
    }

    /**
     * @param  array{start: int, end: int}  $requested
     */
    private function windowLabel(array $requested): string
    {
        return ClockMinutes::toLabel($requested['start']).'–'.ClockMinutes::toLabel($requested['end']);
    }

    private function hoursLabel(float $hours): string
    {
        $rounded = $hours == floor($hours) ? (string) (int) $hours : number_format($hours, 1);

        return $rounded.'h';
    }
}
