<?php

namespace App\Services;

use App\Enums\DspClientAccessLevel;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class DspClientAccessService
{
    public function __construct(private SettingsService $settings) {}

    public function levelFor(User $user, Client $client, ?CarbonInterface $now = null): DspClientAccessLevel
    {
        if (! $user->isDsp() || $user->employee === null) {
            return DspClientAccessLevel::None;
        }

        return $this->levelForEmployee($user->employee, $client, $now);
    }

    public function levelForEmployee(Employee $employee, Client $client, ?CarbonInterface $now = null): DspClientAccessLevel
    {
        $now = $now ?? $this->settings->now();

        if (! $this->hasActiveAssignment($employee, $client)) {
            return DspClientAccessLevel::None;
        }

        $visits = $this->relevantVisits($employee, $client);

        foreach ($visits as $visit) {
            if ($this->grantsFullAccess($visit, $now)) {
                return DspClientAccessLevel::Full;
            }
        }

        return DspClientAccessLevel::Preparation;
    }

    public function hasActiveAssignment(Employee $employee, Client $client): bool
    {
        return $employee->clientAssignments()
            ->active()
            ->where('client_id', $client->id)
            ->exists();
    }

    /**
     * @return Collection<int, ScheduledVisit>
     */
    private function relevantVisits(Employee $employee, Client $client): Collection
    {
        return ScheduledVisit::query()
            ->with(['shiftTemplate', 'visit'])
            ->where('employee_id', $employee->id)
            ->where('client_id', $client->id)
            ->where('status', '!=', ScheduledVisitStatus::Cancelled)
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get();
    }

    private function grantsFullAccess(ScheduledVisit $visit, CarbonInterface $now): bool
    {
        if ($visit->status === ScheduledVisitStatus::InProgress) {
            return true;
        }

        if ($visit->visit !== null && $visit->visit->status === VisitStatus::InProgress) {
            return true;
        }

        if ($visit->status !== ScheduledVisitStatus::Scheduled) {
            return false;
        }

        try {
            $start = $visit->startsAtOn();
            $end = $visit->endsAtOn();
        } catch (RuntimeException) {
            return false;
        }

        $earliest = $start->copy()->subMinutes($visit->earlyStartMinutes());

        return $now->gte($earliest) && $now->lte($end);
    }

    /**
     * @return array{level: string, level_label: string, allows_history: bool, allows_full_profile: bool}
     */
    public function serialize(DspClientAccessLevel $level): array
    {
        return [
            'level' => $level->value,
            'level_label' => $level->label(),
            'allows_history' => $level->allowsHistory(),
            'allows_full_profile' => $level->allowsFullProfile(),
        ];
    }
}
