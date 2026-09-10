<?php

namespace App\Support;

use App\Enums\JobType;
use App\Enums\Role;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\ScheduledVisit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DirectoryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function employeeSummary(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'job_type' => $employee->job_type->value,
            'job_type_label' => Str::headline($employee->job_type->value),
            'employment_status' => $employee->employment_status->value,
            'employment_status_label' => Str::headline($employee->employment_status->value),
            'supervisor_name' => $employee->supervisor?->full_name,
            'has_login' => $employee->user_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function employeeDetail(Employee $employee): array
    {
        return [
            ...self::employeeSummary($employee),
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'date_of_birth' => self::date($employee->date_of_birth),
            'address_line_1' => $employee->address_line_1,
            'address_line_2' => $employee->address_line_2,
            'city' => $employee->city,
            'state' => $employee->state,
            'postal_code' => $employee->postal_code,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_relationship' => $employee->emergency_contact_relationship,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'hired_on' => self::date($employee->hired_on),
            'terminated_on' => self::date($employee->terminated_on),
            'supervisor_id' => $employee->supervisor_id,
            'notes' => $employee->notes,
            'user_id' => $employee->user_id,
            'login_email' => $employee->user?->email,
            'login_role' => $employee->user?->role->value,
        ];
    }

    /**
     * @param  Collection<int, EmployeeCredential>  $credentials
     * @return list<array<string, mixed>>
     */
    public static function credentials(Collection $credentials): array
    {
        return self::values($credentials->map(fn (EmployeeCredential $credential): array => [
            'id' => $credential->id,
            'type' => Str::headline($credential->type->value),
            'name' => $credential->name,
            'issuer' => $credential->issuer,
            'credential_number' => $credential->credential_number,
            'issued_on' => self::date($credential->issued_on),
            'expires_on' => self::date($credential->expires_on),
            'status' => $credential->status->value,
            'status_label' => Str::headline($credential->status->value),
            'notes' => $credential->notes,
        ]));
    }

    /**
     * @param  Collection<int, EmployeeTraining>  $trainings
     * @return list<array<string, mixed>>
     */
    public static function trainings(Collection $trainings): array
    {
        return self::values($trainings->map(fn (EmployeeTraining $training): array => [
            'id' => $training->id,
            'title' => $training->title,
            'provider' => $training->provider,
            'completed_on' => self::date($training->completed_on),
            'expires_on' => self::date($training->expires_on),
            'hours' => $training->hours,
            'status' => $training->status->value,
            'status_label' => Str::headline($training->status->value),
            'notes' => $training->notes,
        ]));
    }

    /**
     * @return list<array{id: string, title: string, detail: string, occurred_on: string}>
     */
    public static function employeeActivity(Employee $employee): array
    {
        $items = [
            [
                'id' => 'hired-'.$employee->id,
                'title' => 'Hired',
                'detail' => 'Employee record created',
                'occurred_on' => self::date($employee->hired_on) ?? self::date($employee->created_at) ?? '',
            ],
        ];

        if ($employee->terminated_on !== null) {
            $items[] = [
                'id' => 'terminated-'.$employee->id,
                'title' => 'Terminated',
                'detail' => 'Employment ended',
                'occurred_on' => self::date($employee->terminated_on) ?? '',
            ];
        }

        foreach ($employee->credentials as $credential) {
            $items[] = [
                'id' => 'credential-'.$credential->id,
                'title' => $credential->name,
                'detail' => 'Credential · '.Str::headline($credential->status->value),
                'occurred_on' => self::date($credential->issued_on) ?? self::date($credential->created_at) ?? '',
            ];
        }

        foreach ($employee->trainings as $training) {
            $items[] = [
                'id' => 'training-'.$training->id,
                'title' => $training->title,
                'detail' => 'Training · '.Str::headline($training->status->value),
                'occurred_on' => self::date($training->completed_on) ?? self::date($training->created_at) ?? '',
            ];
        }

        usort($items, fn (array $left, array $right): int => $right['occurred_on'] <=> $left['occurred_on']);

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientSummary(Client $client): array
    {
        return [
            'id' => $client->id,
            'client_number' => $client->client_number,
            'name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'city' => $client->city,
            'state' => $client->state,
            'status' => $client->status->value,
            'status_label' => Str::headline($client->status->value),
            'supervisor_name' => $client->supervisor?->full_name,
            'active_dsp_count' => $client->relationLoaded('dspAssignments')
                ? $client->dspAssignments->filter(fn (ClientDspAssignment $assignment): bool => $assignment->isActive())->count()
                : $client->dspAssignments()->active()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientDetail(Client $client): array
    {
        return [
            ...self::clientSummary($client),
            'first_name' => $client->first_name,
            'middle_name' => $client->middle_name,
            'last_name' => $client->last_name,
            'date_of_birth' => self::date($client->date_of_birth),
            'address_line_1' => $client->address_line_1,
            'address_line_2' => $client->address_line_2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
            'emergency_contact_name' => $client->emergency_contact_name,
            'emergency_contact_relationship' => $client->emergency_contact_relationship,
            'emergency_contact_phone' => $client->emergency_contact_phone,
            'supervisor_id' => $client->supervisor_id,
            'notes' => $client->notes,
        ];
    }

    /**
     * @param  Collection<int, ClientAuthorization>  $authorizations
     * @return list<array<string, mixed>>
     */
    public static function authorizations(Collection $authorizations): array
    {
        return self::values($authorizations->map(fn (ClientAuthorization $authorization): array => [
            'id' => $authorization->id,
            'authorization_number' => $authorization->authorization_number,
            'payer' => $authorization->payer,
            'service_type' => $authorization->service_type,
            'starts_on' => self::date($authorization->starts_on),
            'ends_on' => self::date($authorization->ends_on),
            'authorized_units' => $authorization->authorized_units,
            'unit' => Str::headline($authorization->unit->value),
            'status' => $authorization->status->value,
            'status_label' => Str::headline($authorization->status->value),
        ]));
    }

    /**
     * @param  Collection<int, CarePlan>  $carePlans
     * @return list<array<string, mixed>>
     */
    public static function carePlans(Collection $carePlans): array
    {
        return self::values($carePlans->map(fn (CarePlan $plan): array => [
            'id' => $plan->id,
            'title' => $plan->title,
            'starts_on' => self::date($plan->starts_on),
            'ends_on' => self::date($plan->ends_on),
            'status' => $plan->status->value,
            'status_label' => Str::headline($plan->status->value),
            'notes' => $plan->notes,
            'tasks' => self::values($plan->taskTemplates->map(fn (CarePlanTaskTemplate $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'recurrence' => Str::headline($task->recurrence->value),
                'is_required' => $task->is_required,
            ])),
        ]));
    }

    /**
     * @param  Collection<int, ClientDspAssignment>  $assignments
     * @return list<array<string, mixed>>
     */
    public static function assignments(Collection $assignments): array
    {
        return self::values($assignments->map(fn (ClientDspAssignment $assignment): array => [
            'id' => $assignment->id,
            'employee_id' => $assignment->employee_id,
            'dsp_name' => $assignment->employee->full_name,
            'dsp_number' => $assignment->employee->employee_number,
            'status' => $assignment->status->value,
            'status_label' => Str::headline($assignment->status->value),
            'started_on' => self::date($assignment->started_on),
            'ended_on' => self::date($assignment->ended_on),
            'notes' => $assignment->notes,
            'is_active' => $assignment->isActive(),
        ]));
    }

    /**
     * @param  Collection<int, ScheduledVisit>  $visits
     * @return list<array<string, mixed>>
     */
    public static function scheduledVisits(Collection $visits): array
    {
        return self::values($visits->map(function (ScheduledVisit $visit): array {
            $start = $visit->starts_at !== null || $visit->shiftTemplate !== null
                ? $visit->startsAtOn()->format('g:i A')
                : null;
            $end = $visit->ends_at !== null || $visit->shiftTemplate !== null
                ? $visit->endsAtOn()->format('g:i A')
                : null;

            return [
                'id' => $visit->id,
                'service_date' => self::date($visit->service_date),
                'service_type' => $visit->service_type,
                'status' => $visit->status->value,
                'status_label' => Str::headline($visit->status->value),
                'time_label' => $start && $end ? $start.' – '.$end : 'Time not set',
                'dsp_name' => $visit->employee->full_name,
                'shift_name' => $visit->shiftTemplate?->name,
            ];
        }));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function supervisorOptions(): array
    {
        return self::values(Employee::query()
            ->where('job_type', JobType::Supervisor)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ]));
    }

    /**
     * @return list<array{id: int, name: string, employee_number: string}>
     */
    public static function dspOptions(): array
    {
        return self::values(Employee::query()
            ->where('job_type', JobType::Dsp)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]));
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public static function linkableUsers(): array
    {
        return self::values(User::query()
            ->whereDoesntHave('employee')
            ->whereIn('role', [Role::Supervisor, Role::Dsp])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ]));
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return list<T>
     */
    private static function values(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return null;
    }
}
