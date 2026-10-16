<?php

namespace App\Services;

use App\Enums\ComplianceApplicability;
use App\Enums\ComplianceDateStatus;
use App\Enums\ComplianceEligibilityTone;
use App\Enums\ComplianceRequirementSource;
use App\Enums\ComplianceVerificationStatus;
use App\Enums\CredentialType;
use App\Enums\JobType;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ComplianceEligibilityService
{
    public function __construct(
        private ComplianceStatusService $classifier,
        private SettingsService $settings,
    ) {}

    /**
     * Authoritative service-eligibility evaluation for an employee.
     *
     * @return array{
     *     eligible_for_service: bool,
     *     hard_stop: bool,
     *     tone: string,
     *     tone_label: string,
     *     summary: string,
     *     warnings: list<array<string, mixed>>,
     *     hard_stops: list<array<string, mixed>>,
     *     requirements: list<array<string, mixed>>,
     *     active_exceptions: list<array<string, mixed>>,
     *     block_reason: string|null
     * }
     */
    public function evaluate(Employee $employee, ?CarbonInterface $at = null): array
    {
        $at ??= $this->settings->now();
        $employee->loadMissing(['credentials', 'trainings']);

        $requirements = $this->applicableRequirements($employee);
        $activeExceptions = $this->activeExceptionsFor($employee, $at);
        $exceptionByRequirement = $activeExceptions->keyBy('compliance_requirement_id');

        $rows = [];
        $warnings = [];
        $hardStops = [];

        foreach ($requirements as $requirement) {
            $row = $this->evaluateRequirement($employee, $requirement, $exceptionByRequirement->get($requirement->id), $at);
            $rows[] = $row;

            if ($row['is_warning']) {
                $warnings[] = $row;
            }

            if ($row['blocks_service']) {
                $hardStops[] = $row;
            }
        }

        $hardStop = $hardStops !== [];
        $tone = $hardStop
            ? ComplianceEligibilityTone::Red
            : ($warnings !== [] ? ComplianceEligibilityTone::Yellow : ComplianceEligibilityTone::Green);

        return [
            'eligible_for_service' => ! $hardStop,
            'hard_stop' => $hardStop,
            'tone' => $tone->value,
            'tone_label' => $tone->label(),
            'summary' => $this->summaryMessage($tone, $hardStops, $warnings),
            'warnings' => $warnings,
            'hard_stops' => $hardStops,
            'requirements' => $rows,
            'active_exceptions' => $activeExceptions
                ->map(fn (ComplianceTemporaryException $exception): array => $this->serializeException($exception))
                ->values()
                ->all(),
            'block_reason' => $hardStop ? $this->blockReason($hardStops) : null,
        ];
    }

    public function canPerformService(Employee $employee, ?CarbonInterface $at = null): bool
    {
        return $this->evaluate($employee, $at)['eligible_for_service'];
    }

    public function serviceBlockReason(Employee $employee, ?CarbonInterface $at = null): ?string
    {
        return $this->evaluate($employee, $at)['block_reason'];
    }

    /**
     * DSP self-view payload: eligibility + own credentials/training with derived conditions.
     *
     * @return array<string, mixed>
     */
    public function selfView(Employee $employee): array
    {
        $evaluation = $this->evaluate($employee);
        $employee->loadMissing(['credentials', 'trainings']);

        $credentials = $employee->credentials
            ->map(function (EmployeeCredential $credential): array {
                $status = $this->classifier->forCredential($credential);

                return [
                    'id' => $credential->id,
                    'kind' => 'credential',
                    'title' => $credential->name,
                    'type' => $credential->type->label(),
                    'type_key' => $credential->type->value,
                    'issuer' => $credential->issuer,
                    'expires_on' => $credential->expires_on?->toDateString(),
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'is_warning' => $status === ComplianceDateStatus::ExpiringSoon,
                    'is_blocking_condition' => in_array($status, [
                        ComplianceDateStatus::Expired,
                        ComplianceDateStatus::Missing,
                        ComplianceDateStatus::Pending,
                        ComplianceDateStatus::Revoked,
                    ], true),
                ];
            })
            ->values()
            ->all();

        $trainings = $employee->trainings
            ->map(function (EmployeeTraining $training): array {
                $status = $this->classifier->forTraining($training);

                return [
                    'id' => $training->id,
                    'kind' => 'training',
                    'title' => $training->title,
                    'type' => 'Training',
                    'type_key' => 'training',
                    'issuer' => $training->provider,
                    'expires_on' => $training->expires_on?->toDateString(),
                    'completed_on' => $training->completed_on?->toDateString(),
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'is_warning' => $status === ComplianceDateStatus::ExpiringSoon,
                    'is_blocking_condition' => in_array($status, [
                        ComplianceDateStatus::Expired,
                        ComplianceDateStatus::Missing,
                        ComplianceDateStatus::InProgress,
                    ], true),
                ];
            })
            ->values()
            ->all();

        return [
            ...$evaluation,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ],
            'expiring_soon_days' => $this->settings->credentialExpiringSoonDays(),
            'timezone' => $this->settings->timezone(),
            'credentials' => $credentials,
            'trainings' => $trainings,
            'reminder_milestones' => [30, 15, 5, 0],
            'read_only' => true,
        ];
    }

    /**
     * @return Collection<int, ComplianceRequirement>
     */
    public function applicableRequirements(Employee $employee): Collection
    {
        return ComplianceRequirement::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (ComplianceRequirement $requirement): bool => $this->appliesTo($requirement, $employee))
            ->values();
    }

    public function appliesTo(ComplianceRequirement $requirement, Employee $employee): bool
    {
        return match ($requirement->applicability) {
            ComplianceApplicability::All => true,
            ComplianceApplicability::Dsp => $employee->job_type === JobType::Dsp,
        };
    }

    /**
     * @param  Collection<int, ComplianceTemporaryException>|null  $exception
     * @return array<string, mixed>
     */
    private function evaluateRequirement(
        Employee $employee,
        ComplianceRequirement $requirement,
        ?ComplianceTemporaryException $exception,
        CarbonInterface $at,
    ): array {
        [$condition, $evidenceLabel, $expiresOn] = $this->deriveCondition($employee, $requirement);
        $unsatisfied = $this->isUnsatisfied($condition);
        $warningOnly = $condition === ComplianceDateStatus::ExpiringSoon
            || ($unsatisfied && ! $requirement->isHardStop());
        $exceptionActive = $exception !== null && $exception->isActiveAt($at);
        $blocks = $requirement->isHardStop() && $unsatisfied && ! $exceptionActive;

        return [
            'requirement_id' => $requirement->id,
            'code' => $requirement->code,
            'name' => $requirement->name,
            'source_kind' => $requirement->source_kind->value,
            'source_key' => $requirement->source_key,
            'enforcement_level' => $requirement->enforcement_level->value,
            'enforcement_label' => $requirement->enforcement_level->label(),
            'override_policy' => $requirement->override_policy->value,
            'override_policy_label' => $requirement->override_policy->label(),
            'allows_temporary_exception' => $requirement->allowsTemporaryException(),
            'condition' => $condition->value,
            'condition_label' => $condition->label(),
            'evidence_label' => $evidenceLabel,
            'expires_on' => $expiresOn,
            'unsatisfied' => $unsatisfied,
            'is_warning' => $warningOnly && ! $blocks,
            'is_hard_stop_condition' => $requirement->isHardStop() && $unsatisfied,
            'exception_active' => $exceptionActive,
            'exception' => $exceptionActive && $exception !== null ? $this->serializeException($exception) : null,
            'blocks_service' => $blocks,
            'underlying_state_unchanged' => true,
        ];
    }

    /**
     * @return array{0: ComplianceDateStatus, 1: string|null, 2: string|null}
     */
    private function deriveCondition(Employee $employee, ComplianceRequirement $requirement): array
    {
        if ($requirement->source_kind === ComplianceRequirementSource::Credential) {
            $type = CredentialType::tryFrom($requirement->source_key);
            if ($type === null) {
                return [ComplianceDateStatus::Missing, null, null];
            }

            $matches = $employee->credentials
                ->filter(fn (EmployeeCredential $row): bool => $row->type === $type)
                ->values();

            if ($matches->isEmpty()) {
                return [ComplianceDateStatus::Missing, null, null];
            }

            $verified = $matches
                ->filter(fn (EmployeeCredential $row): bool => $row->isAuthoritativelyVerified())
                ->sortByDesc(fn (EmployeeCredential $row): string => $row->expires_on?->toDateString() ?? '9999-12-31')
                ->values();

            if ($verified->isNotEmpty()) {
                $credential = $verified->first();

                return [
                    $this->classifier->forCredential($credential),
                    $credential->name,
                    $credential->expires_on?->toDateString(),
                ];
            }

            $pending = $matches->first(
                fn (EmployeeCredential $row): bool => $row->verification_status === ComplianceVerificationStatus::PendingReview,
            );

            if ($pending !== null) {
                return [ComplianceDateStatus::Pending, $pending->name, $pending->expires_on?->toDateString()];
            }

            $rejected = $matches->first();

            return [
                $this->classifier->forCredential($rejected),
                $rejected->name,
                $rejected->expires_on?->toDateString(),
            ];
        }

        $needle = Str::lower(trim($requirement->source_key));
        $matches = $employee->trainings
            ->filter(fn (EmployeeTraining $row): bool => Str::lower(trim($row->title)) === $needle)
            ->values();

        if ($matches->isEmpty()) {
            return [ComplianceDateStatus::Missing, null, null];
        }

        $verified = $matches
            ->filter(fn (EmployeeTraining $row): bool => $row->isAuthoritativelyVerified())
            ->sortByDesc(fn (EmployeeTraining $row): string => $row->expires_on?->toDateString() ?? '9999-12-31')
            ->values();

        if ($verified->isNotEmpty()) {
            $training = $verified->first();

            return [
                $this->classifier->forTraining($training),
                $training->title,
                $training->expires_on?->toDateString(),
            ];
        }

        $pending = $matches->first(
            fn (EmployeeTraining $row): bool => $row->verification_status === ComplianceVerificationStatus::PendingReview,
        );

        if ($pending !== null) {
            return [ComplianceDateStatus::Pending, $pending->title, $pending->expires_on?->toDateString()];
        }

        $training = $matches->first();

        return [
            $this->classifier->forTraining($training),
            $training->title,
            $training->expires_on?->toDateString(),
        ];
    }

    private function isUnsatisfied(ComplianceDateStatus $condition): bool
    {
        return match ($condition) {
            ComplianceDateStatus::Valid, ComplianceDateStatus::ExpiringSoon => false,
            default => true,
        };
    }

    /**
     * @return Collection<int, ComplianceTemporaryException>
     */
    private function activeExceptionsFor(Employee $employee, CarbonInterface $at): Collection
    {
        return ComplianceTemporaryException::query()
            ->with(['requirement', 'createdBy'])
            ->where('employee_id', $employee->id)
            ->activeAt($at)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeException(ComplianceTemporaryException $exception): array
    {
        return [
            'id' => $exception->id,
            'requirement_id' => $exception->compliance_requirement_id,
            'requirement_name' => $exception->requirement?->name,
            'reason' => $exception->reason,
            'starts_at' => $exception->starts_at->toIso8601String(),
            'ends_at' => $exception->ends_at->toIso8601String(),
            'created_by' => $exception->createdBy?->name,
            'created_at' => $exception->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $hardStops
     * @param  list<array<string, mixed>>  $warnings
     */
    private function summaryMessage(ComplianceEligibilityTone $tone, array $hardStops, array $warnings): string
    {
        return match ($tone) {
            ComplianceEligibilityTone::Green => 'No Hard Stop compliance issues. Eligible for service under current requirements.',
            ComplianceEligibilityTone::Yellow => count($warnings) === 1
                ? 'Compliance warning: '.$warnings[0]['name'].' ('.$warnings[0]['condition_label'].'). Not a Hard Stop.'
                : count($warnings).' compliance warnings. Not Hard Stops.',
            ComplianceEligibilityTone::Red => count($hardStops) === 1
                ? 'Hard Stop: '.$hardStops[0]['name'].' ('.$hardStops[0]['condition_label'].').'
                : count($hardStops).' Hard Stop compliance requirements are unsatisfied.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $hardStops
     */
    private function blockReason(array $hardStops): string
    {
        $names = collect($hardStops)->pluck('name')->filter()->values()->all();

        if ($names === []) {
            return 'A Hard Stop compliance requirement is unsatisfied.';
        }

        return 'Hard Stop compliance restriction: '.implode(', ', $names).'.';
    }
}
