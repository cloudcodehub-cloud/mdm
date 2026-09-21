<?php

namespace App\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\AssignmentStatus;
use App\Enums\CarePlanStatus;
use App\Enums\ClientStatus;
use App\Enums\CredentialType;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ProfileCompletenessStatus;
use App\Enums\ProfileItemSeverity;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeEducation;
use App\Models\EmployeeReference;
use App\Models\EmployeeWorkHistory;

class ProfileCompletionService
{

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(Employee $employee): array
    {
        $employee->loadMissing(['credentials', 'educations', 'personalReferences', 'workHistories', 'weeklyAvailabilities', 'user']);

        $items = [];
        $role = $employee->job_type;

        $items[] = $this->item(
            'basic_profile',
            'Basic Profile',
            'profile',
            filled($employee->first_name) && filled($employee->last_name) && $employee->date_of_birth !== null,
            ProfileItemSeverity::Critical,
            route('employees.show', $employee),
        );
        $items[] = $this->item(
            'photo',
            'Photo',
            'profile',
            $employee->hasPhoto(),
            ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'role_employment',
            'Role / Employment',
            'employment',
            $role !== JobType::Other
                && filled($employee->job_title)
                && $employee->hired_on !== null,
            ProfileItemSeverity::Critical,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'contact_address',
            'Contact / Address',
            'contact',
            filled($employee->address_line_1) && filled($employee->city) && filled($employee->state),
            ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'emergency_contact',
            'Emergency Contact',
            'contact',
            filled($employee->emergency_contact_name) && filled($employee->emergency_contact_phone),
            $role === JobType::Dsp ? ProfileItemSeverity::Critical : ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );

        if ($role === JobType::Dsp) {
            $hasAvailability = $employee->weeklyAvailabilities->contains(
                fn ($day): bool => (bool) $day->is_available,
            );
            $items[] = $this->item(
                'availability',
                'Availability',
                'availability',
                $hasAvailability,
                ProfileItemSeverity::Critical,
                route('employees.edit', $employee),
            );
            $items[] = $this->item(
                'transportation',
                'Transportation',
                'transportation',
                $employee->has_drivers_license !== null,
                ProfileItemSeverity::Attention,
                route('employees.edit', $employee),
            );
        }

        $items[] = $this->item(
            'education',
            'Education',
            'education',
            $employee->educations->contains(fn (EmployeeEducation $row): bool => $row->isComplete()),
            ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'references',
            'References',
            'references',
            $employee->personalReferences->filter(fn (EmployeeReference $row): bool => $row->isComplete())->count() >= 2,
            ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'work_history',
            'Work History',
            'work_history',
            $employee->workHistories->contains(fn (EmployeeWorkHistory $row): bool => $row->isComplete()),
            ProfileItemSeverity::Attention,
            route('employees.edit', $employee),
        );
        $items[] = $this->item(
            'credentials',
            'Credentials',
            'credentials',
            $employee->credentials->isNotEmpty(),
            ProfileItemSeverity::Attention,
            route('employees.show', $employee),
        );
        $items[] = $this->item(
            'tb_document',
            'TB document',
            'compliance',
            $this->hasCredentialType($employee, CredentialType::TbScreening),
            ProfileItemSeverity::Attention,
            route('employees.show', $employee),
        );
        $items[] = $this->item(
            'physician_statement',
            'Physician statement',
            'compliance',
            $this->hasCredentialType($employee, CredentialType::PhysicianStatement),
            ProfileItemSeverity::Attention,
            route('employees.show', $employee),
        );
        $items[] = $this->item(
            'security_background',
            'Security / Background',
            'security',
            $employee->ohio_resident_5_years !== null && $employee->has_conviction !== null,
            ProfileItemSeverity::Attention,
            route('employees.show', $employee),
        );

        if ($role->requiresLogin()) {
            $items[] = $this->item(
                'login_account',
                'Login / Account',
                'account',
                $employee->user_id !== null,
                ProfileItemSeverity::Critical,
                route('employees.edit', $employee),
            );
        }

        return $this->report($items);
    }

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        $client->loadMissing(['careServices', 'carePlans', 'authorizations', 'dspAssignments']);

        $active = $client->status === ClientStatus::Active;
        $hasCarePlan = $client->carePlans->contains(
            fn ($plan): bool => $plan->status === CarePlanStatus::Active,
        );
        $hasDsp = $client->dspAssignments->contains(
            fn ($assignment): bool => $assignment->status === AssignmentStatus::Active,
        );
        $hasAuth = $client->authorizations->contains(
            fn ($authorization): bool => $authorization->status === AuthorizationStatus::Active,
        );

        $items = [
            $this->item(
                'basic_profile',
                'Basic Profile',
                'profile',
                filled($client->first_name) && filled($client->last_name),
                ProfileItemSeverity::Critical,
                route('clients.show', $client),
            ),
            $this->item(
                'photo',
                'Photo',
                'profile',
                $client->hasPhoto(),
                ProfileItemSeverity::Attention,
                route('clients.edit', $client),
            ),
            $this->item(
                'contact_address',
                'Contact / Address',
                'contact',
                filled($client->address_line_1) && filled($client->city),
                ProfileItemSeverity::Attention,
                route('clients.edit', $client),
            ),
            $this->item(
                'emergency_contact',
                'Emergency Contact',
                'contact',
                filled($client->emergency_contact_name) && filled($client->emergency_contact_phone),
                ProfileItemSeverity::Attention,
                route('clients.edit', $client),
            ),
            $this->item(
                'supervisor',
                'Assigned Supervisor',
                'assignment',
                $client->supervisor_id !== null,
                $active ? ProfileItemSeverity::Critical : ProfileItemSeverity::Attention,
                route('clients.edit', $client),
            ),
            $this->item(
                'services',
                'Services',
                'care',
                $client->careServices->isNotEmpty(),
                $active ? ProfileItemSeverity::Critical : ProfileItemSeverity::Attention,
                route('clients.setup.edit', $client),
            ),
            $this->item(
                'care_plan',
                'Care Plan',
                'care',
                $hasCarePlan,
                $active ? ProfileItemSeverity::Critical : ProfileItemSeverity::Attention,
                route('clients.show', $client),
            ),
            $this->item(
                'authorizations',
                'Authorizations',
                'care',
                $hasAuth,
                ProfileItemSeverity::Attention,
                route('clients.show', $client),
            ),
            $this->item(
                'assigned_dsps',
                'Assigned DSP(s)',
                'assignment',
                $hasDsp,
                $active ? ProfileItemSeverity::Critical : ProfileItemSeverity::Attention,
                route('clients.show', $client),
            ),
        ];

        return $this->report($items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function report(array $items): array
    {
        $completed = collect($items)->where('complete', true)->count();
        $total = count($items);
        $missingItems = collect($items)->where('complete', false)->values();
        $percent = $total === 0 ? 100 : (int) round(($completed / $total) * 100);
        $band = ProfileCompletenessStatus::fromPercent($percent);

        return [
            'percent' => $percent,
            'completed' => $completed,
            'missing' => $total - $completed,
            'total' => $total,
            'critical_missing' => $missingItems->where('severity', ProfileItemSeverity::Critical->value)->count(),
            'status' => $band->value,
            'status_label' => $band->label(),
            'tone' => $band->tone(),
            'items' => $items,
            'summary' => $this->publicSummary($missingItems->all()),
        ];
    }

    /**
     * @return array{status: string, label: string, tone: string}
     */
    public function statusForPercent(int $percent): array
    {
        $status = ProfileCompletenessStatus::fromPercent($percent);

        return [
            'status' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $missing
     */
    private function publicSummary(array $missing): string
    {
        $labels = collect($missing)
            ->reject(fn (array $item): bool => in_array($item['key'], $this->sensitiveKeys(), true))
            ->pluck('label')
            ->take(4)
            ->all();

        return implode(' · ', $labels);
    }

    /**
     * @return list<string>
     */
    public function sensitiveKeys(): array
    {
        return ['security_background', 'ssn', 'incidents', 'other_names'];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $key,
        string $label,
        string $category,
        bool $complete,
        ProfileItemSeverity $severity,
        ?string $href,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'category' => $category,
            'complete' => $complete,
            'severity' => $severity->value,
            'href' => $href,
        ];
    }

    private function hasCredentialType(Employee $employee, CredentialType $type): bool
    {
        return $employee->credentials->contains(
            fn (EmployeeCredential $credential): bool => $credential->type === $type,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function needsAttention(array $report, EmploymentStatus|ClientStatus|null $status = null): bool
    {
        if ($status instanceof EmploymentStatus && $status === EmploymentStatus::Terminated) {
            return false;
        }

        if ($status instanceof ClientStatus && $status === ClientStatus::Discharged) {
            return false;
        }

        return ($report['missing'] ?? 0) > 0;
    }
}
