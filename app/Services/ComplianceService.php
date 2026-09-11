<?php

namespace App\Services;

use App\Enums\ComplianceDateStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComplianceService
{
    public function __construct(
        private SettingsService $settings,
        private ComplianceStatusService $classifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $employees = $this->workforceQuery($user)
            ->with(['credentials', 'trainings'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $items = $this->items($employees);
        $summary = $this->summary($items);
        $attention = $this->employeeAttention($items);

        return [
            'summary' => $summary,
            'expiring_soon_days' => $this->settings->credentialExpiringSoonDays(),
            'timezone' => $this->settings->timezone(),
            'employees' => $attention,
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function items(Collection $employees): array
    {
        $items = [];

        foreach ($employees as $employee) {
            foreach ($employee->credentials as $credential) {
                $status = $this->classifier->forCredential($credential);
                $items[] = $this->serializeItem(
                    'credential',
                    $credential->id,
                    $employee,
                    $credential->name,
                    $credential->type->value,
                    $credential->expires_on?->toDateString(),
                    $status,
                    $credential->status->value,
                );
            }

            foreach ($employee->trainings as $training) {
                $status = $this->classifier->forTraining($training);
                $items[] = $this->serializeItem(
                    'training',
                    $training->id,
                    $employee,
                    $training->title,
                    'training',
                    $training->expires_on?->toDateString(),
                    $status,
                    $training->status->value,
                );
            }
        }

        usort($items, function (array $left, array $right): int {
            $priority = [
                ComplianceDateStatus::Expired->value => 0,
                ComplianceDateStatus::ExpiringSoon->value => 1,
                ComplianceDateStatus::Pending->value => 2,
                ComplianceDateStatus::Revoked->value => 3,
                ComplianceDateStatus::InProgress->value => 4,
                ComplianceDateStatus::Valid->value => 5,
            ];

            return ($priority[$left['status']] ?? 9) <=> ($priority[$right['status']] ?? 9)
                ?: ($left['employee_name'] <=> $right['employee_name']);
        });

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{valid: int, expiring_soon: int, expired: int, missing: int}
     */
    private function summary(array $items): array
    {
        $counts = [
            'valid' => 0,
            'expiring_soon' => 0,
            'expired' => 0,
            'missing' => 0,
        ];

        foreach ($items as $item) {
            $status = $item['status'];

            if ($status === ComplianceDateStatus::Valid->value) {
                $counts['valid']++;
            } elseif ($status === ComplianceDateStatus::ExpiringSoon->value) {
                $counts['expiring_soon']++;
            } elseif ($status === ComplianceDateStatus::Expired->value) {
                $counts['expired']++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function employeeAttention(array $items): array
    {
        $byEmployee = [];

        foreach ($items as $item) {
            if ($item['status'] === ComplianceDateStatus::Valid->value) {
                continue;
            }

            $id = $item['employee_id'];
            $byEmployee[$id] ??= [
                'id' => $id,
                'name' => $item['employee_name'],
                'employee_number' => $item['employee_number'],
                'expired' => 0,
                'expiring_soon' => 0,
                'other' => 0,
            ];

            if ($item['status'] === ComplianceDateStatus::Expired->value) {
                $byEmployee[$id]['expired']++;
            } elseif ($item['status'] === ComplianceDateStatus::ExpiringSoon->value) {
                $byEmployee[$id]['expiring_soon']++;
            } else {
                $byEmployee[$id]['other']++;
            }
        }

        return array_values($byEmployee);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(
        string $kind,
        int $id,
        Employee $employee,
        string $title,
        string $type,
        ?string $expiresOn,
        ComplianceDateStatus $status,
        string $storedStatus,
    ): array {
        return [
            'id' => $kind.'-'.$id,
            'kind' => $kind,
            'record_id' => $id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'title' => $title,
            'type' => $type,
            'expires_on' => $expiresOn,
            'status' => $status->value,
            'status_label' => $status->label(),
            'stored_status' => $storedStatus,
        ];
    }

    /**
     * @return Builder<Employee>
     */
    public function workforceQuery(User $user): Builder
    {
        $query = Employee::query()->where('employment_status', EmploymentStatus::Active);

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSupervisor()) {
            $employee = $user->employee;

            if ($employee === null) {
                return Employee::query()->whereRaw('1 = 0');
            }

            return $query
                ->where('supervisor_id', $employee->id)
                ->where('job_type', JobType::Dsp);
        }

        if ($user->isDsp() && $user->employee !== null) {
            return $query->where('id', $user->employee->id);
        }

        return Employee::query()->whereRaw('1 = 0');
    }
}
