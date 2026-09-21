<?php

namespace App\Support;

use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\Role;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeEducation;
use App\Models\EmployeeReference;
use App\Models\EmployeeSecurityIncident;
use App\Models\EmployeeTraining;
use App\Models\EmployeeWorkHistory;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\SkipReason;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use App\Services\ProfilePhotoService;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DirectoryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function employeeSummary(Employee $employee, ?ProfilePhotoService $photos = null): array
    {
        $photos ??= app(ProfilePhotoService::class);

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->primaryPhone(),
            'job_title' => $employee->job_title,
            'job_type' => $employee->job_type->value,
            'job_type_label' => $employee->job_type->label(),
            'employment_status' => $employee->employment_status->value,
            'employment_status_label' => Str::headline($employee->employment_status->value),
            'supervisor_name' => $employee->supervisor?->full_name,
            'has_login' => $employee->user_id !== null,
            'photo_url' => $photos->employeeUrl($employee),
            'initials' => $employee->initials(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function employeeDetail(Employee $employee, bool $includeSensitive = false, ?ProfilePhotoService $photos = null): array
    {
        $photos ??= app(ProfilePhotoService::class);

        $detail = [
            ...self::employeeSummary($employee, $photos),
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'date_of_birth' => self::date($employee->date_of_birth),
            'home_phone' => $employee->home_phone,
            'cell_phone' => $employee->cell_phone ?: $employee->phone,
            'alternate_phone' => $employee->alternate_phone,
            'address_line_1' => $employee->address_line_1,
            'address_line_2' => $employee->address_line_2,
            'city' => $employee->city,
            'state' => $employee->state,
            'postal_code' => $employee->postal_code,
            'previous_address_line_1' => $employee->previous_address_line_1,
            'previous_city' => $employee->previous_city,
            'previous_state' => $employee->previous_state,
            'previous_postal_code' => $employee->previous_postal_code,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_relationship' => $employee->emergency_contact_relationship,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'hired_on' => self::date($employee->hired_on),
            'terminated_on' => self::date($employee->terminated_on),
            'employment_type' => $employee->employment_type?->value,
            'preferred_shift_type' => $employee->preferred_shift_type,
            'desired_hours_per_week' => $employee->desired_hours_per_week,
            'willing_long_term' => $employee->willing_long_term,
            'willing_short_term' => $employee->willing_short_term,
            'willing_pets' => $employee->willing_pets,
            'willing_smoke' => $employee->willing_smoke,
            'how_heard' => $employee->how_heard,
            'employment_interest' => $employee->employment_interest,
            'has_drivers_license' => $employee->has_drivers_license,
            'license_state' => $employee->license_state,
            'license_number' => $employee->license_number,
            'vehicle_make_year' => $employee->vehicle_make_year,
            'insurance_company' => $employee->insurance_company,
            'insurance_policy_number' => $employee->insurance_policy_number,
            'has_moving_violations' => $employee->has_moving_violations,
            'moving_violations_description' => $employee->moving_violations_description,
            'license_ever_suspended' => $employee->license_ever_suspended,
            'license_suspension_explanation' => $employee->license_suspension_explanation,
            'may_contact_current_employer' => $employee->may_contact_current_employer,
            'supervisor_id' => $employee->supervisor_id,
            'notes' => $employee->notes,
            'user_id' => $employee->user_id,
            'login_email' => $employee->user?->email,
            'login_role' => $employee->user?->role->value,
            'educations' => $employee->relationLoaded('educations')
                ? self::educations($employee)
                : [],
            'references' => $employee->relationLoaded('personalReferences')
                ? self::references($employee)
                : [],
            'work_histories' => $employee->relationLoaded('workHistories')
                ? self::workHistories($employee)
                : [],
            'availability_days' => $employee->relationLoaded('weeklyAvailabilities')
                ? self::values($employee->weeklyAvailabilities->map(fn ($day): array => [
                    'weekday' => $day->weekday,
                    'is_available' => $day->is_available,
                    'starts_at' => self::inputTime($day->starts_at),
                    'ends_at' => self::inputTime($day->ends_at),
                    'preferred_daypart' => $day->preferred_daypart?->value,
                ]))
                : [],
        ];

        if ($includeSensitive) {
            $detail['ohio_resident_5_years'] = $employee->ohio_resident_5_years;
            $detail['residence_history'] = $employee->residence_history;
            $detail['used_other_names'] = $employee->used_other_names;
            $detail['other_names'] = $employee->other_names;
            $detail['ssn_masked'] = SensitiveValue::maskSsn($employee->ssn);
            $detail['has_ssn'] = filled($employee->ssn);
            $detail['alternate_ssn_masked'] = SensitiveValue::maskSsn($employee->alternate_ssn);
            $detail['has_conviction'] = $employee->has_conviction;
            $detail['security_comments'] = $employee->security_comments;
            $detail['incidents'] = $employee->relationLoaded('securityIncidents')
                ? self::values($employee->securityIncidents->map(fn (EmployeeSecurityIncident $incident): array => [
                    'incident' => $incident->incident,
                    'city_state' => $incident->city_state,
                    'charge' => $incident->charge,
                ]))
                : [];
        }

        return $detail;
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
            'has_document' => filled($credential->document_path),
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
    public static function clientSummary(Client $client, ?ProfilePhotoService $photos = null): array
    {
        $photos ??= app(ProfilePhotoService::class);

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
            'photo_url' => $photos->clientUrl($client),
            'initials' => $client->initials(),
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
            'care_services' => $client->relationLoaded('careServices')
                ? CareServicePresenter::options($client->careServices)
                : [],
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
            'tasks' => self::values($plan->taskTemplates
                ->where('is_active', true)
                ->map(fn (CarePlanTaskTemplate $task): array => [
                    'id' => $task->id,
                    'catalog_item_id' => $task->catalog_item_id,
                    'title' => $task->title,
                    'instructions' => $task->instructions,
                    'recurrence' => $task->recurrence->value,
                    'recurrence_label' => $task->recurrence->label(),
                    'recurrence_detail' => $task->recurrence_detail,
                    'weekdays' => $task->weekdays,
                    'interval_weeks' => $task->interval_weeks,
                    'preferred_timing' => $task->preferred_timing?->value,
                    'preferred_timing_label' => $task->preferred_timing?->label(),
                    'is_required' => $task->is_required,
                    'note_required' => $task->note_required,
                    'can_skip' => $task->can_skip,
                    'is_critical' => $task->is_critical,
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
        return self::values($visits->map(fn (ScheduledVisit $visit): array => self::scheduledVisitSummary($visit)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheduledVisitSummary(ScheduledVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'service_date' => self::date($visit->service_date),
            'service_type' => $visit->service_type,
            'services' => $visit->relationLoaded('careServices')
                ? CareServicePresenter::options($visit->careServices)
                : [],
            'status' => $visit->status->value,
            'status_label' => Str::headline($visit->status->value),
            'time_label' => self::visitTimeLabel($visit),
            'spans_overnight' => $visit->spansOvernight(),
            'dsp_name' => $visit->employee->full_name,
            'client_name' => $visit->client->full_name,
            'client_id' => $visit->client_id,
            'employee_id' => $visit->employee_id,
            'shift_name' => $visit->shiftTemplate?->name,
            'supervisor_name' => $visit->supervisor?->full_name,
            'needs_attention' => $visit->needs_attention,
            'attention_reason' => $visit->attention_reason,
            'series_id' => $visit->series_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheduledVisitDetail(ScheduledVisit $visit): array
    {
        return [
            ...self::scheduledVisitSummary($visit),
            'notes' => $visit->notes,
            'starts_at' => self::inputTime($visit->starts_at),
            'ends_at' => self::inputTime($visit->ends_at),
            'shift_template_id' => $visit->shift_template_id,
            'supervisor_id' => $visit->supervisor_id,
            'client' => [
                'id' => $visit->client->id,
                'name' => $visit->client->full_name,
                'client_number' => $visit->client->client_number,
            ],
            'employee' => [
                'id' => $visit->employee->id,
                'name' => $visit->employee->full_name,
                'employee_number' => $visit->employee->employee_number,
            ],
            'supervisor' => $visit->supervisor === null ? null : [
                'id' => $visit->supervisor->id,
                'name' => $visit->supervisor->full_name,
            ],
            'shift_template' => $visit->shiftTemplate === null ? null : [
                'id' => $visit->shiftTemplate->id,
                'name' => $visit->shiftTemplate->name,
            ],
            'active_visit_id' => $visit->visit?->id,
            'one_off_tasks' => $visit->relationLoaded('oneOffTasks')
                ? self::values($visit->oneOffTasks->map(fn ($task): array => [
                    'id' => $task->id,
                    'catalog_item_id' => $task->catalog_item_id,
                    'title' => $task->title,
                    'instructions' => $task->instructions,
                    'note_required' => $task->note_required,
                    'is_required' => $task->is_required,
                ]))
                : [],
            'task_overrides' => $visit->relationLoaded('taskOverrides')
                ? self::values($visit->taskOverrides->map(fn ($row): array => [
                    'care_plan_task_template_id' => $row->care_plan_task_template_id,
                    'included' => $row->included,
                    'exclusion_reason' => $row->exclusion_reason,
                ]))
                : [],
            'series' => $visit->series === null ? null : [
                'id' => $visit->series->id,
                'pattern' => $visit->series->pattern->value,
                'starts_on' => self::date($visit->series->starts_on),
                'ends_on' => self::date($visit->series->ends_on),
            ],
            'assignments' => $visit->relationLoaded('assignments')
                ? self::values($visit->assignments->map(fn ($row): array => [
                    'id' => $row->id,
                    'employee_name' => $row->employee->full_name,
                    'kind' => $row->kind->value,
                    'reason' => $row->reason,
                    'assigned_at' => $row->assigned_at->toDateTimeString(),
                    'ended_at' => $row->ended_at?->toDateTimeString(),
                    'assigned_by' => $row->assignedBy?->name,
                ]))
                : [],
            'created_by_name' => $visit->createdBy?->name,
            'updated_by_name' => $visit->updatedBy?->name,
            'cancellation_reason' => $visit->cancellation_reason,
            'replacement_reason' => $visit->replacement_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function visitDetail(Visit $visit): array
    {
        $scheduled = $visit->scheduledVisit;
        $tasks = $visit->tasks;
        $completed = $tasks->where('status', VisitTaskStatus::Completed)->count();
        $skipped = $tasks->where('status', VisitTaskStatus::Skipped)->count();
        $pending = $tasks->where('status', VisitTaskStatus::Pending)->count();
        $pendingRequired = $tasks
            ->where('status', VisitTaskStatus::Pending)
            ->where('is_required', true)
            ->count();

        return [
            'id' => $visit->id,
            'status' => $visit->status->value,
            'status_label' => Str::headline($visit->status->value),
            'service_type' => $visit->service_type,
            'clocked_in_at' => $visit->clocked_in_at->toIso8601String(),
            'clocked_in_at_label' => app(SettingsService::class)->formatTime($visit->clocked_in_at),
            'clocked_out_at' => $visit->clocked_out_at?->toIso8601String(),
            'clocked_out_at_label' => $visit->clocked_out_at === null
                ? null
                : app(SettingsService::class)->formatTime($visit->clocked_out_at),
            'duration_label' => self::visitDurationLabel($visit),
            'location_method' => $visit->clock_in_location_method->value,
            'location_status' => $visit->clock_in_location_status->value,
            'location_status_label' => self::gpsStatusLabel($visit->clock_in_location_status->value),
            'unavailable_reason' => $visit->clock_in_unavailable_reason,
            'latitude' => $visit->clock_in_latitude,
            'longitude' => $visit->clock_in_longitude,
            'accuracy' => $visit->clock_in_accuracy,
            'clock_out_location_method' => $visit->clock_out_location_method?->value,
            'clock_out_location_status' => $visit->clock_out_location_status?->value,
            'clock_out_location_status_label' => $visit->clock_out_location_status === null
                ? null
                : self::gpsStatusLabel($visit->clock_out_location_status->value),
            'clock_out_unavailable_reason' => $visit->clock_out_unavailable_reason,
            'visit_notes' => $visit->visit_notes,
            'handover_note' => $visit->handover_note,
            'unfinished_required_acknowledged' => $visit->unfinished_required_acknowledged,
            'task_summary' => [
                'total' => $tasks->count(),
                'completed' => $completed,
                'skipped' => $skipped,
                'pending' => $pending,
                'pending_required' => $pendingRequired,
            ],
            'client' => [
                'id' => $visit->client->id,
                'name' => $visit->client->full_name,
                'client_number' => $visit->client->client_number,
            ],
            'employee' => [
                'id' => $visit->employee->id,
                'name' => $visit->employee->full_name,
                'employee_number' => $visit->employee->employee_number,
            ],
            'scheduled_visit' => [
                'id' => $scheduled->id,
                'service_date' => self::date($scheduled->service_date),
                'time_label' => self::visitTimeLabel($scheduled),
                'shift_name' => $scheduled->shiftTemplate?->name,
                'status' => $scheduled->status->value,
            ],
            'tasks' => self::visitTasks($tasks),
            'exceptions' => self::visitExceptions($visit->relationLoaded('exceptions') ? $visit->exceptions : $visit->exceptions()->with(['visitTask', 'reviewedBy', 'resolvedBy'])->get()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function activeVisitSummary(Visit $visit): array
    {
        $visit->loadMissing('tasks');
        $tasks = $visit->tasks;
        $completed = $tasks->where('status', VisitTaskStatus::Completed)->count();
        $skipped = $tasks->where('status', VisitTaskStatus::Skipped)->count();
        $pending = $tasks->where('status', VisitTaskStatus::Pending)->count();
        $total = $tasks->count();

        return [
            'id' => $visit->id,
            'scheduled_visit_id' => $visit->scheduled_visit_id,
            'service_type' => $visit->service_type,
            'clocked_in_at' => $visit->clocked_in_at->toIso8601String(),
            'location_status' => $visit->clock_in_location_status->value,
            'location_status_label' => self::locationStatusLabel($visit),
            'client' => [
                'id' => $visit->client->id,
                'name' => $visit->client->full_name,
                'client_number' => $visit->client->client_number,
            ],
            'task_progress' => [
                'completed' => $completed,
                'pending' => $pending,
                'skipped' => $skipped,
                'total' => $total,
                'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clockInVisitSummary(ScheduledVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'service_date' => self::date($visit->service_date),
            'service_type' => $visit->service_type,
            'time_label' => self::visitTimeLabel($visit),
            'client' => [
                'id' => $visit->client->id,
                'name' => $visit->client->full_name,
                'client_number' => $visit->client->client_number,
            ],
        ];
    }

    /**
     * @param  Collection<int, VisitTask>  $tasks
     * @return list<array<string, mixed>>
     */
    public static function visitTasks(Collection $tasks): array
    {
        return self::values($tasks->map(fn (VisitTask $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'instructions' => $task->instructions,
            'recurrence' => $task->recurrence->value,
            'recurrence_label' => $task->recurrence_detail
                ? $task->recurrence->label().' · '.$task->recurrence_detail
                : $task->recurrence->label(),
            'preferred_timing' => $task->preferred_timing?->value,
            'preferred_timing_label' => $task->preferred_timing?->label(),
            'is_required' => $task->is_required,
            'note_required' => $task->note_required,
            'can_skip' => $task->can_skip,
            'is_critical' => $task->is_critical,
            'status' => $task->status->value,
            'status_label' => Str::headline($task->status->value),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'completed_at_label' => $task->completed_at === null
                ? null
                : app(SettingsService::class)->formatTime($task->completed_at),
            'skipped_at' => $task->skipped_at?->toIso8601String(),
            'skip_reason_id' => $task->skip_reason_id,
            'skip_reason_name' => $task->skipReason?->name,
            'skip_comment' => $task->skip_comment,
            'completion_note' => $task->completion_note,
            'is_one_off' => $task->scheduled_visit_one_off_task_id !== null,
        ]));
    }

    /**
     * @param  Collection<int, VisitException>|iterable<VisitException>  $exceptions
     * @return list<array<string, mixed>>
     */
    public static function visitExceptions(iterable $exceptions): array
    {
        return self::values(collect($exceptions)->map(fn (VisitException $exception): array => self::visitException($exception)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function visitException(VisitException $exception): array
    {
        $visit = $exception->relationLoaded('visit') ? $exception->visit : null;
        $settings = app(SettingsService::class);

        return [
            'id' => $exception->id,
            'visit_id' => $exception->visit_id,
            'visit_task_id' => $exception->visit_task_id,
            'type' => $exception->type->value,
            'type_label' => $exception->type->label(),
            'priority' => $exception->type->priority(),
            'is_high_priority' => $exception->type->isHighPriority(),
            'status' => $exception->status->value,
            'status_label' => $exception->status->label(),
            'message' => $exception->message,
            'context' => $exception->context,
            'task_title' => $exception->visitTask?->title,
            'review_notes' => $exception->review_notes,
            'resolution_notes' => $exception->resolution_notes,
            'reviewed_at_label' => $exception->reviewed_at === null ? null : $settings->formatTime($exception->reviewed_at),
            'resolved_at_label' => $exception->resolved_at === null ? null : $settings->formatTime($exception->resolved_at),
            'reviewed_by_name' => $exception->reviewedBy?->name,
            'resolved_by_name' => $exception->resolvedBy?->name,
            'status_history' => $exception->status_history ?? [],
            'created_at_label' => $exception->created_at === null ? null : $settings->formatTime($exception->created_at),
            'client_name' => $visit?->client?->full_name,
            'dsp_name' => $visit?->employee?->full_name,
            'service_type' => $visit?->service_type,
        ];
    }

    /**
     * @return list<array{id: int, name: string, code: string, requires_comment: bool, requires_explanation: bool}>
     */
    public static function skipReasons(): array
    {
        return self::values(SkipReason::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SkipReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'code' => $reason->code,
                'requires_comment' => $reason->requires_comment,
                'requires_explanation' => $reason->requiresExplanation(),
            ]));
    }

    public static function locationStatusLabel(Visit $visit): string
    {
        return self::gpsStatusLabel($visit->clock_in_location_status->value);
    }

    public static function gpsStatusLabel(string $status): string
    {
        return match ($status) {
            'captured' => 'GPS captured',
            'denied' => 'GPS denied',
            'unsupported' => 'GPS unsupported',
            default => 'GPS unavailable',
        };
    }

    public static function visitTimeLabel(ScheduledVisit $visit): string
    {
        if ($visit->starts_at === null && $visit->shiftTemplate === null) {
            return 'Time not set';
        }

        $settings = app(SettingsService::class);
        $label = $settings->formatTime($visit->startsAtOn()).' – '.$settings->formatTime($visit->endsAtOn());

        if ($visit->spansOvernight()) {
            return $label.' next day';
        }

        return $label;
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
        return self::dspFilterOptions(null);
    }

    /**
     * @return list<array{id: int, name: string, employee_number: string}>
     */
    public static function dspFilterOptions(?User $user): array
    {
        return self::values(Employee::query()
            ->where('job_type', JobType::Dsp)
            ->when($user !== null && ! $user->isAdmin(), function ($query) use ($user): void {
                if ($user->isDsp() && $user->employee) {
                    $query->where('id', $user->employee->id);

                    return;
                }

                $query->where(function ($builder) use ($user): void {
                    $builder->visibleTo($user)
                        ->orWhereIn('id', ClientDspAssignment::query()
                            ->active()
                            ->whereIn('client_id', Client::query()->visibleTo($user)->select('id'))
                            ->select('employee_id'));
                });
            })
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
     * @return list<array{id: int, name: string, client_number: string}>
     */
    public static function clientFilterOptions(User $user): array
    {
        return self::values(Client::query()
            ->visibleTo($user)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->full_name,
                'client_number' => $client->client_number,
            ]));
    }

    /**
     * @return list<array{id: int, name: string, client_number: string, supervisor_id: int|null, services: list<array{id: int, name: string, slug: string}>}>
     */
    public static function schedulingClientOptions(User $user): array
    {
        return self::values(Client::query()
            ->with(['supervisor', 'careServices' => fn ($query) => $query->active()])
            ->visibleTo($user)
            ->where('status', ClientStatus::Active)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->full_name,
                'client_number' => $client->client_number,
                'supervisor_id' => $client->supervisor_id,
                'supervisor_name' => $client->supervisor?->full_name,
                'services' => CareServicePresenter::options($client->careServices),
            ]));
    }

    /**
     * @return list<array{id: int, name: string, employee_number: string, assigned_client_ids: list<int>}>
     */
    public static function schedulingDspOptions(User $user, ?Employee $current = null): array
    {
        $dsps = Employee::query()
            ->with(['clientAssignments' => fn ($query) => $query->active()])
            ->where('job_type', JobType::Dsp)
            ->where('employment_status', EmploymentStatus::Active)
            ->when(! $user->isAdmin(), function ($query) use ($user): void {
                $query->where(function ($builder) use ($user): void {
                    $builder->visibleTo($user)
                        ->orWhereIn('id', ClientDspAssignment::query()
                            ->active()
                            ->whereIn('client_id', Client::query()->visibleTo($user)->select('id'))
                            ->select('employee_id'));
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($current !== null && $dsps->doesntContain(fn (Employee $employee): bool => $employee->id === $current->id)) {
            $current->loadMissing(['clientAssignments' => fn ($query) => $query->active()]);
            $dsps->prepend($current);
        }

        return self::values($dsps->map(function (Employee $employee): array {
            $assignedClientIds = [];

            foreach ($employee->clientAssignments as $assignment) {
                if ($assignment->isActive()) {
                    $assignedClientIds[] = $assignment->client_id;
                }
            }

            return [
                'id' => $employee->id,
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'assigned_client_ids' => $assignedClientIds,
            'photo_url' => app(ProfilePhotoService::class)->employeeUrl($employee),
            'initials' => $employee->initials(),
            ];
        }));
    }

    /**
     * @return list<array{id: int, name: string, starts_at: string, ends_at: string, spans_overnight: bool}>
     */
    public static function shiftTemplateOptions(?ShiftTemplate $current = null): array
    {
        $templates = ShiftTemplate::query()
            ->active()
            ->orderBy('starts_at')
            ->get();

        if ($current !== null && $templates->doesntContain(fn (ShiftTemplate $template): bool => $template->id === $current->id)) {
            $templates->prepend($current);
        }

        return self::values($templates->map(fn (ShiftTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'starts_at' => self::inputTime($template->starts_at) ?? $template->starts_at,
            'ends_at' => self::inputTime($template->ends_at) ?? $template->ends_at,
            'spans_overnight' => $template->spansOvernight(),
        ]));
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    public static function linkableUsers(): array
    {
        return self::values(User::query()
            ->whereDoesntHave('employee')
            ->whereIn('role', [Role::Supervisor, Role::Dsp, Role::Admin])
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
     * @return list<array<string, mixed>>
     */
    private static function educations(Employee $employee): array
    {
        return self::values($employee->educations->map(fn (EmployeeEducation $row): array => [
            'level' => $row->level->value,
            'institution_name' => $row->institution_name,
            'city' => $row->city,
            'state' => $row->state,
            'country' => $row->country,
            'graduated' => $row->graduated,
            'years_completed' => $row->years_completed,
            'degree' => $row->degree,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function references(Employee $employee): array
    {
        return self::values($employee->personalReferences->map(fn (EmployeeReference $row): array => [
            'name' => $row->name,
            'address' => $row->address,
            'home_phone' => $row->home_phone,
            'work_phone' => $row->work_phone,
            'relationship' => $row->relationship,
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function workHistories(Employee $employee): array
    {
        return self::values($employee->workHistories->map(fn (EmployeeWorkHistory $row): array => [
            'started_on' => self::date($row->started_on),
            'ended_on' => self::date($row->ended_on),
            'job_title' => $row->job_title,
            'employer' => $row->employer,
            'employer_phone' => $row->employer_phone,
            'employer_address' => $row->employer_address,
            'reason_for_leaving' => $row->reason_for_leaving,
            'job_duties' => $row->job_duties,
        ]));
    }

    private static function visitDurationLabel(Visit $visit): ?string
    {
        if ($visit->clocked_out_at === null) {
            return null;
        }

        $minutes = (int) $visit->clocked_in_at->diffInMinutes($visit->clocked_out_at);
        $hours = intdiv(max(0, $minutes), 60);
        $remainder = max(0, $minutes) % 60;

        return sprintf('%dh %02dm', $hours, $remainder);
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

    private static function inputTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }
}
