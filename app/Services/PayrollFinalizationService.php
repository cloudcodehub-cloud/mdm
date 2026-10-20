<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\PayrollPeriodAuditAction;
use App\Enums\PayrollPeriodScope;
use App\Enums\PayrollPeriodStatus;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodAudit;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 03 §§14–15 / MDM 05 §M — finalize, lock, Admin reopen, re-finalize.
 * Hours truth remains effective attendance; original EVV is never rewritten here.
 */
class PayrollFinalizationService
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * @param  array{
     *     period_start: string,
     *     period_end: string,
     *     scope?: string,
     *     employee_id?: int|null,
     *     scheduled_visit_id?: int|null,
     *     finalize_note?: string|null,
     *     confirm?: bool
     * }  $payload
     */
    public function finalize(User $actor, array $payload): PayrollPeriod
    {
        $this->assertAdmin($actor);

        if (! filter_var($payload['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'confirm' => 'Explicit confirmation is required to finalize payroll.',
            ]);
        }

        [$start, $end, $scope, $employeeId, $visitId, $scopeKey] = $this->resolveScope($payload);

        $existing = PayrollPeriod::query()->where('scope_key', $scopeKey)->first();

        if ($existing !== null && $existing->isLocked()) {
            throw ValidationException::withMessages([
                'period_start' => 'This payroll scope is already finalized.',
            ]);
        }

        $this->assertReadyToFinalize($start, $end, $scope, $employeeId, $visitId);

        $note = $this->normalizeNote($payload['finalize_note'] ?? null);
        $now = now();

        return DB::transaction(function () use ($existing, $start, $end, $scope, $scopeKey, $employeeId, $visitId, $actor, $note, $now): PayrollPeriod {
            $period = $existing ?? new PayrollPeriod([
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'scope' => $scope,
                'scope_key' => $scopeKey,
                'employee_id' => $employeeId,
                'scheduled_visit_id' => $visitId,
            ]);

            $action = $period->exists
                ? PayrollPeriodAuditAction::ReFinalized
                : PayrollPeriodAuditAction::Finalized;

            $period->fill([
                'status' => PayrollPeriodStatus::Finalized,
                'finalized_at' => $now,
                'finalized_by_user_id' => $actor->id,
                'finalize_note' => $note,
                'reopened_at' => null,
                'reopened_by_user_id' => null,
                'reopen_reason' => null,
            ]);
            $period->save();

            $this->audit($period, $action, $actor, $note, [
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'scope' => $scope->value,
            ]);

            return $period->refresh();
        });
    }

    /**
     * @param  array{reopen_reason: string, confirm?: bool}  $payload
     */
    public function reopen(User $actor, PayrollPeriod $period, array $payload): PayrollPeriod
    {
        $this->assertAdmin($actor);

        if (! $period->isLocked()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Only a finalized payroll record may be reopened.',
            ]);
        }

        if (! filter_var($payload['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'confirm' => 'Explicit confirmation is required to reopen finalized payroll.',
            ]);
        }

        $reason = trim((string) ($payload['reopen_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reopen_reason' => 'A reason is required to reopen finalized payroll.',
            ]);
        }

        $now = now();

        return DB::transaction(function () use ($period, $actor, $reason, $now): PayrollPeriod {
            $period->forceFill([
                'status' => PayrollPeriodStatus::ReopenedForCorrection,
                'reopened_at' => $now,
                'reopened_by_user_id' => $actor->id,
                'reopen_reason' => $reason,
            ])->save();

            $this->audit($period, PayrollPeriodAuditAction::Reopened, $actor, $reason, [
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'scope' => $period->scope->value,
            ]);

            return $period->refresh();
        });
    }

    /**
     * @param  array{finalize_note?: string|null, confirm?: bool}  $payload
     */
    public function reFinalize(User $actor, PayrollPeriod $period, array $payload): PayrollPeriod
    {
        $this->assertAdmin($actor);

        if (! $period->isReopened()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Only a payroll record in Reopened for Correction may be re-finalized.',
            ]);
        }

        return $this->finalize($actor, [
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'scope' => $period->scope->value,
            'employee_id' => $period->employee_id,
            'scheduled_visit_id' => $period->scheduled_visit_id,
            'finalize_note' => $payload['finalize_note'] ?? $period->finalize_note,
            'confirm' => $payload['confirm'] ?? false,
        ]);
    }

    public function lockingPeriodFor(ScheduledVisit $scheduledVisit): ?PayrollPeriod
    {
        $date = $scheduledVisit->service_date->toDateString();
        $employeeId = $scheduledVisit->employee_id;

        $candidates = PayrollPeriod::query()
            ->where('status', PayrollPeriodStatus::Finalized)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $period) {
            if ($period->scope === PayrollPeriodScope::Visit
                && (int) $period->scheduled_visit_id === (int) $scheduledVisit->id) {
                return $period;
            }

            if ($period->scope === PayrollPeriodScope::Employee
                && $employeeId !== null
                && (int) $period->employee_id === (int) $employeeId) {
                return $period;
            }

            if ($period->scope === PayrollPeriodScope::Period) {
                return $period;
            }
        }

        return null;
    }

    public function assertNotLockedForCorrection(ScheduledVisit $scheduledVisit): void
    {
        $lock = $this->lockingPeriodFor($scheduledVisit);

        if ($lock === null) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'This attendance record is in a finalized payroll period ('.$lock->period_start->toDateString()
                .'–'.$lock->period_end->toDateString().'). An Admin must reopen the payroll record before corrections can change effective hours.',
        ]);
    }

    /**
     * @param  array{from: string, to: string, employee_id?: string}  $filters
     * @return array<string, mixed>|null
     */
    public function serializeForReport(User $user, array $filters): ?array
    {
        if (! $user->isAdmin()) {
            return null;
        }

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($from === '' || $to === '') {
            return [
                'available' => false,
                'message' => 'Select a from/to pay-period range to finalize or reopen payroll.',
                'record' => null,
                'can' => [
                    'finalize' => false,
                    'reopen' => false,
                    're_finalize' => false,
                ],
            ];
        }

        $employeeId = ($filters['employee_id'] ?? '') !== '' ? (int) $filters['employee_id'] : null;
        $scope = $employeeId !== null ? PayrollPeriodScope::Employee : PayrollPeriodScope::Period;
        $scopeKey = $this->scopeKey($scope, $from, $to, $employeeId, null);
        $record = PayrollPeriod::query()->where('scope_key', $scopeKey)->first();

        return [
            'available' => true,
            'message' => null,
            'scope' => $scope->value,
            'scope_label' => $scope->label(),
            'period_start' => $from,
            'period_end' => $to,
            'employee_id' => $employeeId,
            'record' => $record === null ? null : $this->serializePeriod($record),
            'export_ready' => $record?->isExportReady() ?? false,
            'can' => [
                'finalize' => $record === null || $record->isReopened(),
                'reopen' => $record !== null && $record->isLocked(),
                're_finalize' => $record !== null && $record->isReopened(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePeriod(PayrollPeriod $period): array
    {
        $period->loadMissing(['finalizedBy', 'reopenedBy', 'audits.actor']);

        return [
            'id' => $period->id,
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'scope' => $period->scope->value,
            'scope_label' => $period->scope->label(),
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'export_ready' => $period->isExportReady(),
            'finalized_at' => $period->finalized_at !== null
                ? $this->settings->formatDateTime($period->finalized_at)
                : null,
            'finalized_by_name' => $period->finalizedBy?->name,
            'finalize_note' => $period->finalize_note,
            'reopened_at' => $period->reopened_at !== null
                ? $this->settings->formatDateTime($period->reopened_at)
                : null,
            'reopened_by_name' => $period->reopenedBy?->name,
            'reopen_reason' => $period->reopen_reason,
            'audits' => $period->audits
                ->sortByDesc(fn (PayrollPeriodAudit $audit): int => $audit->created_at?->getTimestamp() ?? 0)
                ->values()
                ->map(fn (PayrollPeriodAudit $audit): array => [
                    'id' => $audit->id,
                    'action' => $audit->action->value,
                    'action_label' => $audit->action->label(),
                    'actor_name' => $audit->actor?->name,
                    'reason' => $audit->reason,
                    'created_at' => $audit->created_at !== null
                        ? $this->settings->formatDateTime($audit->created_at)
                        : null,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: PayrollPeriodScope, 3: int|null, 4: int|null, 5: string}
     */
    private function resolveScope(array $payload): array
    {
        try {
            $start = Carbon::parse((string) $payload['period_start'], $this->settings->timezone())->startOfDay();
            $end = Carbon::parse((string) $payload['period_end'], $this->settings->timezone())->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'period_start' => 'A valid pay-period start and end are required.',
            ]);
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'period_end' => 'Period end must be on or after period start.',
            ]);
        }

        $scope = PayrollPeriodScope::tryFrom((string) ($payload['scope'] ?? PayrollPeriodScope::Period->value))
            ?? PayrollPeriodScope::Period;
        $employeeId = isset($payload['employee_id']) && $payload['employee_id'] !== null && $payload['employee_id'] !== ''
            ? (int) $payload['employee_id']
            : null;
        $visitId = isset($payload['scheduled_visit_id']) && $payload['scheduled_visit_id'] !== null && $payload['scheduled_visit_id'] !== ''
            ? (int) $payload['scheduled_visit_id']
            : null;

        if ($scope === PayrollPeriodScope::Employee) {
            if ($employeeId === null || ! Employee::query()->whereKey($employeeId)->exists()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Select the employee whose finalized timesheet scope to lock.',
                ]);
            }
            $visitId = null;
        } elseif ($scope === PayrollPeriodScope::Visit) {
            if ($visitId === null || ! ScheduledVisit::query()->whereKey($visitId)->exists()) {
                throw ValidationException::withMessages([
                    'scheduled_visit_id' => 'Select the timesheet / visit record to finalize.',
                ]);
            }
            $employeeId = null;
        } else {
            $employeeId = null;
            $visitId = null;
        }

        $scopeKey = $this->scopeKey(
            $scope,
            $start->toDateString(),
            $end->toDateString(),
            $employeeId,
            $visitId,
        );

        return [$start, $end, $scope, $employeeId, $visitId, $scopeKey];
    }

    private function scopeKey(
        PayrollPeriodScope $scope,
        string $start,
        string $end,
        ?int $employeeId,
        ?int $visitId,
    ): string {
        return match ($scope) {
            PayrollPeriodScope::Period => "period:{$start}:{$end}",
            PayrollPeriodScope::Employee => "employee:{$start}:{$end}:{$employeeId}",
            PayrollPeriodScope::Visit => "visit:{$visitId}",
        };
    }

    private function assertReadyToFinalize(
        CarbonInterface $start,
        CarbonInterface $end,
        PayrollPeriodScope $scope,
        ?int $employeeId,
        ?int $visitId,
    ): void {
        $visitQuery = Visit::query()
            ->where('status', VisitStatus::InProgress)
            ->whereHas('scheduledVisit', function ($query) use ($start, $end, $scope, $employeeId, $visitId): void {
                $query->whereDate('service_date', '>=', $start->toDateString())
                    ->whereDate('service_date', '<=', $end->toDateString());

                if ($scope === PayrollPeriodScope::Employee && $employeeId !== null) {
                    $query->where('employee_id', $employeeId);
                }

                if ($scope === PayrollPeriodScope::Visit && $visitId !== null) {
                    $query->whereKey($visitId);
                }
            });

        if ($visitQuery->exists()) {
            throw ValidationException::withMessages([
                'period_start' => 'Unresolved Active Visits remain in this period. Active hours are provisional and cannot be finalized.',
            ]);
        }

        $pendingQuery = AttendanceCorrection::query()
            ->where('status', AttendanceCorrectionStatus::Pending)
            ->whereHas('scheduledVisit', function ($query) use ($start, $end, $scope, $employeeId, $visitId): void {
                $query->whereDate('service_date', '>=', $start->toDateString())
                    ->whereDate('service_date', '<=', $end->toDateString());

                if ($scope === PayrollPeriodScope::Employee && $employeeId !== null) {
                    $query->where('employee_id', $employeeId);
                }

                if ($scope === PayrollPeriodScope::Visit && $visitId !== null) {
                    $query->whereKey($visitId);
                }
            });

        if ($pendingQuery->exists()) {
            throw ValidationException::withMessages([
                'period_start' => 'Pending attendance corrections that could change worked time must be resolved before finalization.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function audit(
        PayrollPeriod $period,
        PayrollPeriodAuditAction $action,
        User $actor,
        ?string $reason,
        array $meta = [],
    ): void {
        PayrollPeriodAudit::query()->create([
            'payroll_period_id' => $period->id,
            'action' => $action,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    private function assertAdmin(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Only Admins may finalize or reopen payroll records.',
            ]);
        }
    }

    private function normalizeNote(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
