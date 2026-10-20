<?php

namespace App\Services;

use App\Enums\JobType;
use App\Enums\TemporarySupervisorCoverageAuditAction;
use App\Enums\TemporarySupervisorCoverageMode;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\TemporarySupervisorCoverage;
use App\Models\TemporarySupervisorCoverageAudit;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 03 §10 / MASTER §4.4 — Admin temporary Supervisor coverage.
 * Does not rewrite permanent supervisor_id ownership or grant private-message access.
 * Redistribution removals that touch Active Visits are deferred; revoke/expire remain immediate.
 */
class TemporarySupervisorCoverageService
{
    public function __construct(private SettingsService $settings) {}

    /** @var array<int, list<TemporarySupervisorCoverage>> */
    private array $activeByCoveringCache = [];

    /**
     * @param  array{
     *     covering_supervisor_id: int,
     *     mode?: string,
     *     client_ids?: list<int>|null,
     *     employee_ids?: list<int>|null,
     *     starts_at: string,
     *     ends_at: string,
     *     note?: string|null
     * }  $payload
     */
    public function grant(User $actor, Employee $original, array $payload): TemporarySupervisorCoverage
    {
        $this->assertAdmin($actor);
        $this->assertSupervisorEmployee($original);

        $covering = $this->resolveCoveringSupervisor((int) $payload['covering_supervisor_id'], $original);
        [$starts, $ends] = $this->parseWindow($payload['starts_at'] ?? null, $payload['ends_at'] ?? null);
        $mode = TemporarySupervisorCoverageMode::tryFrom((string) ($payload['mode'] ?? TemporarySupervisorCoverageMode::FullCaseload->value))
            ?? TemporarySupervisorCoverageMode::FullCaseload;
        [$clientIds, $employeeIds] = $this->resolveScope($mode, $original, $payload);

        return DB::transaction(function () use ($actor, $original, $covering, $mode, $clientIds, $employeeIds, $starts, $ends, $payload): TemporarySupervisorCoverage {
            $coverage = TemporarySupervisorCoverage::query()->create([
                'original_supervisor_id' => $original->id,
                'covering_supervisor_id' => $covering->id,
                'mode' => $mode,
                'client_ids' => $clientIds,
                'employee_ids' => $employeeIds,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'note' => $this->normalizeNote($payload['note'] ?? null),
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit($coverage, TemporarySupervisorCoverageAuditAction::Granted, $actor, $coverage->note, [
                'mode' => $mode->value,
                'starts_at' => $starts->toIso8601String(),
                'ends_at' => $ends->toIso8601String(),
            ]);

            $this->forgetCache();

            return $coverage->refresh();
        });
    }

    /**
     * @param  array{
     *     mode?: string,
     *     client_ids?: list<int>|null,
     *     employee_ids?: list<int>|null,
     *     starts_at?: string,
     *     ends_at?: string,
     *     note?: string|null
     * }  $payload
     */
    public function modify(User $actor, TemporarySupervisorCoverage $coverage, array $payload): TemporarySupervisorCoverage
    {
        $this->assertAdmin($actor);

        if ($coverage->isRevoked() || $coverage->isExpired()) {
            throw ValidationException::withMessages([
                'coverage' => 'Expired coverage cannot be backdated or revived. Grant a new coverage period instead.',
            ]);
        }

        $original = $coverage->originalSupervisor()->firstOrFail();
        $mode = isset($payload['mode'])
            ? (TemporarySupervisorCoverageMode::tryFrom((string) $payload['mode']) ?? $coverage->mode)
            : $coverage->mode;

        $starts = array_key_exists('starts_at', $payload) && filled($payload['starts_at'])
            ? $this->parseInstant((string) $payload['starts_at'], 'starts_at')
            : $coverage->starts_at;
        $ends = array_key_exists('ends_at', $payload) && filled($payload['ends_at'])
            ? $this->parseInstant((string) $payload['ends_at'], 'ends_at')
            : $coverage->ends_at;

        if ($ends->lte($starts)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Coverage end must be after the start.',
            ]);
        }

        [$intendedClients, $intendedEmployees] = $this->resolveScope($mode, $original, [
            'client_ids' => $payload['client_ids'] ?? $coverage->client_ids,
            'employee_ids' => $payload['employee_ids'] ?? $coverage->employee_ids,
        ]);

        [$deferredClients, $deferredEmployees] = $this->planDeferredRemovals(
            $coverage,
            $original,
            $mode,
            $intendedClients,
            $intendedEmployees,
        );

        return DB::transaction(function () use (
            $actor,
            $coverage,
            $mode,
            $intendedClients,
            $intendedEmployees,
            $deferredClients,
            $deferredEmployees,
            $starts,
            $ends,
            $payload,
        ): TemporarySupervisorCoverage {
            // Intended scope is stored separately from deferred removals.
            // Effective auth = intended ∪ deferred until Active Visits finish.
            $coverage->forceFill([
                'mode' => $mode,
                'client_ids' => $intendedClients,
                'employee_ids' => $intendedEmployees,
                'deferred_remove_client_ids' => $deferredClients === [] ? null : $deferredClients,
                'deferred_remove_employee_ids' => $deferredEmployees === [] ? null : $deferredEmployees,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'note' => array_key_exists('note', $payload)
                    ? $this->normalizeNote($payload['note'])
                    : $coverage->note,
            ])->save();

            $this->audit($coverage, TemporarySupervisorCoverageAuditAction::Modified, $actor, $coverage->note, [
                'mode' => $mode->value,
                'starts_at' => $starts->toIso8601String(),
                'ends_at' => $ends->toIso8601String(),
                'deferred_remove_client_ids' => $deferredClients,
                'deferred_remove_employee_ids' => $deferredEmployees,
            ]);

            $this->forgetCache();

            return $coverage->refresh();
        });
    }

    /**
     * @param  array{revoke_reason: string}  $payload
     */
    public function revoke(User $actor, TemporarySupervisorCoverage $coverage, array $payload): TemporarySupervisorCoverage
    {
        $this->assertAdmin($actor);

        if ($coverage->isRevoked()) {
            throw ValidationException::withMessages([
                'coverage' => 'This coverage is already revoked.',
            ]);
        }

        $reason = trim((string) ($payload['revoke_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'revoke_reason' => 'A revocation reason is required.',
            ]);
        }

        return DB::transaction(function () use ($actor, $coverage, $reason): TemporarySupervisorCoverage {
            // MDM 03 §10 — revoke immediately removes temporary access (not deferred).
            $coverage->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revoke_reason' => $reason,
                'deferred_remove_client_ids' => null,
                'deferred_remove_employee_ids' => null,
            ])->save();

            $this->audit($coverage, TemporarySupervisorCoverageAuditAction::Revoked, $actor, $reason);

            $this->forgetCache();

            return $coverage->refresh();
        });
    }

    /**
     * After an Active Visit ends, drop deferred removals that no longer have an
     * in-progress Visit. Does not prolong revoked/expired access or invent authority.
     */
    public function applyDeferredRemovalsAfterVisit(Visit $visit): int
    {
        $clientId = (int) $visit->client_id;
        $employeeId = (int) $visit->employee_id;
        $applied = 0;

        $coverages = TemporarySupervisorCoverage::query()
            ->currentlyActive()
            ->where(function ($query): void {
                $query->whereNotNull('deferred_remove_client_ids')
                    ->orWhereNotNull('deferred_remove_employee_ids');
            })
            ->get()
            ->filter(function (TemporarySupervisorCoverage $coverage) use ($clientId, $employeeId): bool {
                $clients = array_map('intval', $coverage->deferred_remove_client_ids ?? []);
                $employees = array_map('intval', $coverage->deferred_remove_employee_ids ?? []);

                return in_array($clientId, $clients, true)
                    || in_array($employeeId, $employees, true);
            });

        foreach ($coverages as $coverage) {
            $priorClients = array_map('intval', $coverage->deferred_remove_client_ids ?? []);
            $priorEmployees = array_map('intval', $coverage->deferred_remove_employee_ids ?? []);

            $deferredClients = array_values(array_filter(
                $priorClients,
                fn (int $id): bool => $this->clientHasActiveVisit($id),
            ));
            $deferredEmployees = array_values(array_filter(
                $priorEmployees,
                fn (int $id): bool => $this->dspHasActiveVisit($id),
            ));

            if ($deferredClients === $priorClients && $deferredEmployees === $priorEmployees) {
                continue;
            }

            $coverage->forceFill([
                'deferred_remove_client_ids' => $deferredClients === [] ? null : $deferredClients,
                'deferred_remove_employee_ids' => $deferredEmployees === [] ? null : $deferredEmployees,
            ])->save();

            $applied++;
        }

        if ($applied > 0) {
            $this->forgetCache();
        }

        return $applied;
    }

    /**
     * @return Collection<int, TemporarySupervisorCoverage>
     */
    public function activeForCovering(User $user): Collection
    {
        if (! $user->isSupervisor() || $user->employee === null) {
            return collect();
        }

        $coveringId = $user->employee->id;

        if (! array_key_exists($coveringId, $this->activeByCoveringCache)) {
            $this->activeByCoveringCache[$coveringId] = TemporarySupervisorCoverage::query()
                ->currentlyActive()
                ->where('covering_supervisor_id', $coveringId)
                ->get()
                ->all();
        }

        return collect($this->activeByCoveringCache[$coveringId]);
    }

    /**
     * @return list<int>
     */
    public function coveredOriginalSupervisorIds(User $user): array
    {
        return $this->activeForCovering($user)
            ->pluck('original_supervisor_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Client IDs reachable via active temporary coverage (full or selective).
     *
     * @return list<int>
     */
    public function coveredClientIds(User $user): array
    {
        $ids = [];

        foreach ($this->activeForCovering($user) as $coverage) {
            if ($coverage->mode === TemporarySupervisorCoverageMode::FullCaseload) {
                $ids = array_merge(
                    $ids,
                    Client::query()
                        ->where('supervisor_id', $coverage->original_supervisor_id)
                        ->pluck('id')
                        ->all(),
                );
            } else {
                $ids = array_merge(
                    $ids,
                    array_map('intval', $coverage->client_ids ?? []),
                    array_map('intval', $coverage->deferred_remove_client_ids ?? []),
                );
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * DSP employee IDs reachable via active temporary coverage.
     *
     * @return list<int>
     */
    public function coveredDspIds(User $user): array
    {
        $ids = [];

        foreach ($this->activeForCovering($user) as $coverage) {
            if ($coverage->mode === TemporarySupervisorCoverageMode::FullCaseload) {
                $ids = array_merge(
                    $ids,
                    Employee::query()
                        ->where('supervisor_id', $coverage->original_supervisor_id)
                        ->where('job_type', JobType::Dsp)
                        ->pluck('id')
                        ->all(),
                );
            } else {
                $ids = array_merge(
                    $ids,
                    array_map('intval', $coverage->employee_ids ?? []),
                    array_map('intval', $coverage->deferred_remove_employee_ids ?? []),
                );
            }
        }

        return array_values(array_unique($ids));
    }

    public function coversClient(User $user, Client $client): bool
    {
        return in_array((int) $client->id, $this->coveredClientIds($user), true);
    }

    public function coversDsp(User $user, Employee $dsp): bool
    {
        return in_array((int) $dsp->id, $this->coveredDspIds($user), true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForSupervisor(Employee $original): array
    {
        return TemporarySupervisorCoverage::query()
            ->with(['coveringSupervisor', 'createdBy', 'revokedBy', 'audits.actor'])
            ->where('original_supervisor_id', $original->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TemporarySupervisorCoverage $coverage): array => $this->serialize($coverage))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(TemporarySupervisorCoverage $coverage): array
    {
        $coverage->loadMissing(['coveringSupervisor', 'createdBy', 'revokedBy', 'audits.actor']);

        return [
            'id' => $coverage->id,
            'mode' => $coverage->mode->value,
            'mode_label' => $coverage->mode->label(),
            'status_label' => $coverage->statusLabel(),
            'is_active' => $coverage->isCurrentlyActive(),
            'covering_supervisor_id' => $coverage->covering_supervisor_id,
            'covering_supervisor_name' => $coverage->coveringSupervisor?->full_name,
            'client_ids' => $coverage->client_ids ?? [],
            'employee_ids' => $coverage->employee_ids ?? [],
            'deferred_remove_client_ids' => $coverage->deferred_remove_client_ids ?? [],
            'deferred_remove_employee_ids' => $coverage->deferred_remove_employee_ids ?? [],
            'starts_at' => $this->settings->formatDateTime($coverage->starts_at),
            'ends_at' => $this->settings->formatDateTime($coverage->ends_at),
            'starts_at_input' => $this->settings->datetimeLocalValue($coverage->starts_at),
            'ends_at_input' => $this->settings->datetimeLocalValue($coverage->ends_at),
            'note' => $coverage->note,
            'created_by_name' => $coverage->createdBy?->name,
            'revoked_at' => $coverage->revoked_at !== null
                ? $this->settings->formatDateTime($coverage->revoked_at)
                : null,
            'revoke_reason' => $coverage->revoke_reason,
            'revoked_by_name' => $coverage->revokedBy?->name,
            'audits' => $coverage->audits
                ->sortByDesc(fn (TemporarySupervisorCoverageAudit $audit): int => $audit->created_at?->getTimestamp() ?? 0)
                ->values()
                ->map(fn (TemporarySupervisorCoverageAudit $audit): array => [
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

    private function resolveCoveringSupervisor(int $coveringId, Employee $original): Employee
    {
        $covering = Employee::query()->whereKey($coveringId)->first();

        if ($covering === null || $covering->job_type !== JobType::Supervisor) {
            throw ValidationException::withMessages([
                'covering_supervisor_id' => 'Select an active Supervisor to provide coverage.',
            ]);
        }

        if ($covering->id === $original->id) {
            throw ValidationException::withMessages([
                'covering_supervisor_id' => 'A Supervisor cannot cover their own permanent caseload via temporary coverage.',
            ]);
        }

        return $covering;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: list<int>|null, 1: list<int>|null}
     */
    private function resolveScope(TemporarySupervisorCoverageMode $mode, Employee $original, array $payload): array
    {
        if ($mode === TemporarySupervisorCoverageMode::FullCaseload) {
            return [null, null];
        }

        $clientIds = array_values(array_unique(array_map(
            'intval',
            array_filter($payload['client_ids'] ?? [], fn ($id): bool => (int) $id > 0),
        )));
        $employeeIds = array_values(array_unique(array_map(
            'intval',
            array_filter($payload['employee_ids'] ?? [], fn ($id): bool => (int) $id > 0),
        )));

        if ($clientIds === [] && $employeeIds === []) {
            throw ValidationException::withMessages([
                'client_ids' => 'Selective coverage requires at least one client or DSP from the original caseload.',
            ]);
        }

        $ownedClients = Client::query()
            ->where('supervisor_id', $original->id)
            ->whereIn('id', $clientIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($ownedClients) !== count($clientIds)) {
            throw ValidationException::withMessages([
                'client_ids' => 'Selective clients must belong to the original Supervisor caseload.',
            ]);
        }

        $ownedDsps = Employee::query()
            ->where('supervisor_id', $original->id)
            ->where('job_type', JobType::Dsp)
            ->whereIn('id', $employeeIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($ownedDsps) !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Selective DSPs must report to the original Supervisor.',
            ]);
        }

        return [$clientIds, $employeeIds];
    }

    /**
     * MDM 03 §10 — when redistribution removes a relationship still in an Active Visit,
     * keep temporary access for that relationship until the visit ends. Unrelated removals
     * take effect immediately. Distinct from permanent supervisor_id reassignment.
     *
     * @param  list<int>|null  $intendedClients
     * @param  list<int>|null  $intendedEmployees
     * @return array{0: list<int>, 1: list<int>}
     */
    private function planDeferredRemovals(
        TemporarySupervisorCoverage $coverage,
        Employee $original,
        TemporarySupervisorCoverageMode $mode,
        ?array $intendedClients,
        ?array $intendedEmployees,
    ): array {
        if ($mode === TemporarySupervisorCoverageMode::FullCaseload) {
            return [[], []];
        }

        $previousClients = $this->previousEffectiveClientIds($coverage, $original);
        $previousEmployees = $this->previousEffectiveEmployeeIds($coverage, $original);
        $nextClients = array_map('intval', $intendedClients ?? []);
        $nextEmployees = array_map('intval', $intendedEmployees ?? []);

        $removedClients = array_values(array_diff($previousClients, $nextClients));
        $removedEmployees = array_values(array_diff($previousEmployees, $nextEmployees));

        $deferredClients = array_values(array_filter(
            $removedClients,
            fn (int $id): bool => $this->clientHasActiveVisit($id),
        ));
        $deferredEmployees = array_values(array_filter(
            $removedEmployees,
            fn (int $id): bool => $this->dspHasActiveVisit($id),
        ));

        return [$deferredClients, $deferredEmployees];
    }

    /**
     * @return list<int>
     */
    private function previousEffectiveClientIds(TemporarySupervisorCoverage $coverage, Employee $original): array
    {
        if ($coverage->mode === TemporarySupervisorCoverageMode::FullCaseload) {
            return Client::query()
                ->where('supervisor_id', $original->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return array_values(array_unique(array_merge(
            array_map('intval', $coverage->client_ids ?? []),
            array_map('intval', $coverage->deferred_remove_client_ids ?? []),
        )));
    }

    /**
     * @return list<int>
     */
    private function previousEffectiveEmployeeIds(TemporarySupervisorCoverage $coverage, Employee $original): array
    {
        if ($coverage->mode === TemporarySupervisorCoverageMode::FullCaseload) {
            return Employee::query()
                ->where('supervisor_id', $original->id)
                ->where('job_type', JobType::Dsp)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return array_values(array_unique(array_merge(
            array_map('intval', $coverage->employee_ids ?? []),
            array_map('intval', $coverage->deferred_remove_employee_ids ?? []),
        )));
    }

    private function clientHasActiveVisit(int $clientId): bool
    {
        return Visit::query()
            ->where('client_id', $clientId)
            ->where('status', VisitStatus::InProgress)
            ->exists();
    }

    private function dspHasActiveVisit(int $employeeId): bool
    {
        return Visit::query()
            ->where('employee_id', $employeeId)
            ->where('status', VisitStatus::InProgress)
            ->exists();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseWindow(?string $startsAt, ?string $endsAt): array
    {
        $starts = $this->parseInstant($startsAt, 'starts_at');
        $ends = $this->parseInstant($endsAt, 'ends_at');

        if ($ends->lte($starts)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Coverage end must be after the start.',
            ]);
        }

        return [$starts, $ends];
    }

    private function parseInstant(?string $value, string $field): Carbon
    {
        $parsed = $this->settings->parseLocalDateTime($value);

        if ($parsed === null) {
            throw ValidationException::withMessages([
                $field => 'Provide a valid coverage date and time.',
            ]);
        }

        return Carbon::parse($parsed);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function audit(
        TemporarySupervisorCoverage $coverage,
        TemporarySupervisorCoverageAuditAction $action,
        User $actor,
        ?string $reason,
        array $meta = [],
    ): void {
        TemporarySupervisorCoverageAudit::query()->create([
            'temporary_supervisor_coverage_id' => $coverage->id,
            'action' => $action,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }

    private function assertAdmin(User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'coverage' => 'Only Admins may administer temporary Supervisor coverage.',
            ]);
        }
    }

    private function assertSupervisorEmployee(Employee $employee): void
    {
        if ($employee->job_type !== JobType::Supervisor) {
            throw ValidationException::withMessages([
                'supervisor' => 'Temporary coverage applies to Supervisor employees only.',
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

    private function forgetCache(): void
    {
        $this->activeByCoveringCache = [];
    }
}
