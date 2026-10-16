<?php

namespace App\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\ClientAuthorization;
use App\Models\ScheduledVisit;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuthorizationUsageService
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceStatusService $attendance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(ClientAuthorization $authorization, ?CarbonInterface $on = null): array
    {
        $today = $on?->toDateString() ?? $this->settings->today();
        $authorized = (float) $authorization->authorized_units;
        $actual = $this->actualUsed($authorization);
        $projected = $this->projectedFuture($authorization, $today);
        $projectedTotal = round($actual + $projected, 2);
        $remaining = round($authorized - $projectedTotal, 2);

        $daysToExpiry = null;
        $approachingExpiration = false;
        $expired = false;

        if ($authorization->ends_on !== null) {
            $daysToExpiry = (int) Carbon::parse($today)
                ->startOfDay()
                ->diffInDays($authorization->ends_on->copy()->startOfDay(), false);
            $expired = $daysToExpiry < 0
                || $authorization->status === AuthorizationStatus::Expired
                || ($authorization->ends_on->toDateString() < $today);
            $window = $this->settings->credentialExpiringSoonDays();
            $approachingExpiration = ! $expired && $daysToExpiry <= $window;
        }

        $warningRatio = (float) config('mdm.authorization.remaining_capacity_warning_ratio', 0.10);
        $capacityPressure = $authorized > 0
            && $remaining <= max(0.01, $authorized * $warningRatio);

        $hardBlock = $expired
            || $authorization->status === AuthorizationStatus::Cancelled
            || $authorization->status === AuthorizationStatus::Exhausted
            || ($authorized > 0 && $actual >= $authorized);

        $warnings = [];

        if ($approachingExpiration) {
            $warnings[] = 'Authorization expiration is approaching.';
        }

        if ($capacityPressure && ! $hardBlock) {
            $warnings[] = 'Projected authorization capacity is under pressure.';
        }

        if ($expired) {
            $warnings[] = 'Authorization is expired; future unauthorized service is blocked.';
        } elseif ($hardBlock) {
            $warnings[] = 'Authorized units are exhausted; future unauthorized service is blocked.';
        }

        return [
            'authorization_id' => $authorization->id,
            'authorized_units' => $authorized,
            'unit' => $authorization->unit->value,
            'unit_label' => $authorization->unit->name,
            'actual_used' => $actual,
            'projected_future' => $projected,
            'projected_total' => $projectedTotal,
            'remaining' => $remaining,
            'days_to_expiry' => $daysToExpiry,
            'approaching_expiration' => $approachingExpiration,
            'capacity_pressure' => $capacityPressure,
            'hard_block' => $hardBlock,
            'expired' => $expired,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  Collection<int, ClientAuthorization>  $authorizations
     * @return list<array<string, mixed>>
     */
    public function summarizeMany(Collection $authorizations): array
    {
        return $authorizations->map(fn (ClientAuthorization $authorization): array => $this->summarize($authorization))
            ->values()
            ->all();
    }

    public function blocksFutureService(ClientAuthorization $authorization, ?CarbonInterface $serviceDate = null): bool
    {
        $summary = $this->summarize($authorization, $serviceDate);

        if ($summary['hard_block']) {
            return true;
        }

        if ($authorization->ends_on !== null && $serviceDate !== null) {
            return $authorization->ends_on->lt($serviceDate->copy()->startOfDay());
        }

        return false;
    }

    public function actualUsed(ClientAuthorization $authorization): float
    {
        $visits = Visit::query()
            ->where('client_id', $authorization->client_id)
            ->where('service_type', $authorization->service_type)
            ->where('status', VisitStatus::Completed)
            ->whereDate('clocked_in_at', '>=', $authorization->starts_on->toDateString())
            ->when(
                $authorization->ends_on !== null,
                fn ($query) => $query->whereDate('clocked_in_at', '<=', $authorization->ends_on->toDateString()),
            )
            ->with('scheduledVisit.attendanceCorrections')
            ->get();

        return $this->sumVisits($visits, $authorization->unit, actual: true);
    }

    public function projectedFuture(ClientAuthorization $authorization, ?string $today = null): float
    {
        $today ??= $this->settings->today();

        $scheduled = ScheduledVisit::query()
            ->where('client_id', $authorization->client_id)
            ->where('service_type', $authorization->service_type)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $today)
            ->when(
                $authorization->ends_on !== null,
                fn ($query) => $query->whereDate('service_date', '<=', $authorization->ends_on->toDateString()),
            )
            ->whereDate('service_date', '>=', $authorization->starts_on->toDateString())
            ->with('shiftTemplate')
            ->get();

        return $this->sumScheduled($scheduled, $authorization->unit);
    }

    /**
     * @param  Collection<int, Visit>  $visits
     */
    private function sumVisits(Collection $visits, AuthorizationUnit $unit, bool $actual): float
    {
        $total = 0.0;

        foreach ($visits as $visit) {
            $scheduled = $visit->scheduledVisit;

            if ($scheduled === null) {
                continue;
            }

            $total += match ($unit) {
                AuthorizationUnit::Visit => 1.0,
                AuthorizationUnit::Day => 1.0,
                AuthorizationUnit::Hour => $this->hoursForScheduled($scheduled, actual: $actual),
            };
        }

        return round($total, 2);
    }

    /**
     * @param  Collection<int, ScheduledVisit>  $scheduled
     */
    private function sumScheduled(Collection $scheduled, AuthorizationUnit $unit): float
    {
        $total = 0.0;

        foreach ($scheduled as $visit) {
            $total += match ($unit) {
                AuthorizationUnit::Visit => 1.0,
                AuthorizationUnit::Day => 1.0,
                AuthorizationUnit::Hour => round($visit->durationMinutes() / 60, 2),
            };
        }

        return round($total, 2);
    }

    private function hoursForScheduled(ScheduledVisit $scheduled, bool $actual): float
    {
        if ($actual) {
            $minutes = $this->attendance->workedMinutes($scheduled);

            if ($minutes !== null) {
                return round($minutes / 60, 2);
            }
        }

        return round($scheduled->durationMinutes() / 60, 2);
    }
}
