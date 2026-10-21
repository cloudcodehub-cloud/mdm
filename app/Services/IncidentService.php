<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\InAppNotificationType;
use App\Enums\IncidentHistoryAction;
use App\Enums\IncidentPartyKind;
use App\Enums\IncidentStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\IncidentHistoryEvent;
use App\Models\IncidentParty;
use App\Models\User;
use App\Models\Visit;
use App\Services\Documents\RetainedDocumentService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(
        private SettingsService $settings,
        private InAppNotificationService $notifications,
        private RetainedDocumentService $retainedDocuments,
    ) {}

    public function acknowledgmentThresholdMinutes(): int
    {
        $configured = (int) $this->settings->current()->incident_acknowledgment_minutes;

        if ($configured < 1) {
            return (int) config('mdm.incident.acknowledgment_minutes_default', 60);
        }

        return $configured;
    }

    /**
     * @return list<array{value: string, label: string, subcategories: list<array{value: string, label: string}>}>
     */
    public function categoryOptions(): array
    {
        /** @var array<string, list<string>> $categories */
        $categories = config('mdm.incident.categories', []);

        return array_map(
            fn (string $category, array $subs): array => [
                'value' => $category,
                'label' => $category,
                'subcategories' => array_map(
                    fn (string $sub): array => ['value' => $sub, 'label' => $sub],
                    $subs,
                ),
            ],
            array_keys($categories),
            array_values($categories),
        );
    }

    public function activeDraftForVisit(Visit $visit): ?Incident
    {
        return Incident::query()
            ->activeDraft()
            ->where('visit_id', $visit->id)
            ->where('reporter_employee_id', $visit->employee_id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(User $user, Visit $visit, array $payload, ?Incident $incident = null): Incident
    {
        $employee = $user->employee;

        if ($employee === null || $visit->employee_id !== $employee->id) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $visit, $payload, $incident, $employee): Incident {
            if ($incident !== null) {
                /** @var Incident $incident */
                $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

                if (! $incident->isDraft() || $incident->reporter_employee_id !== $employee->id) {
                    throw ValidationException::withMessages([
                        'incident' => 'Only the reporting DSP may edit this Incident draft.',
                    ]);
                }
            } else {
                $existing = $this->activeDraftForVisit($visit);

                if ($existing !== null) {
                    $incident = Incident::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                }
            }

            $attributes = $this->reporterAttributes($visit, $payload);
            $attributes['status'] = IncidentStatus::Draft;
            $attributes['tracking_number'] = null;
            $attributes['submitted_at'] = null;

            if ($incident === null) {
                $incident = Incident::query()->create($attributes);
                $this->recordHistory(
                    $incident,
                    IncidentHistoryAction::DraftCreated,
                    'Incident draft created.',
                    $user,
                );
            } else {
                $incident->fill($attributes)->save();
                $this->recordHistory(
                    $incident,
                    IncidentHistoryAction::DraftUpdated,
                    'Incident draft updated.',
                    $user,
                );
            }

            $this->syncParties($incident, $payload['parties'] ?? null);

            return $incident->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(User $user, Visit $visit, array $payload, ?Incident $incident = null): Incident
    {
        $employee = $user->employee;

        if ($employee === null || $visit->employee_id !== $employee->id) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $visit, $payload, $incident, $employee): Incident {
            if ($incident !== null) {
                /** @var Incident $incident */
                $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

                if (! $incident->isDraft() || $incident->reporter_employee_id !== $employee->id) {
                    throw ValidationException::withMessages([
                        'incident' => 'Only an unfinished draft owned by the reporting DSP may be submitted.',
                    ]);
                }
            } else {
                $existing = $this->activeDraftForVisit($visit);
                $incident = $existing !== null
                    ? Incident::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail()
                    : null;
            }

            $attributes = $this->reporterAttributes($visit, $payload);
            $now = $this->settings->now();
            $attributes['status'] = IncidentStatus::Submitted;
            $attributes['submitted_at'] = $now;
            $attributes['responsible_supervisor_id'] = $visit->client->supervisor_id
                ?? $visit->scheduledVisit?->supervisor_id;

            if ($incident === null) {
                $incident = Incident::query()->create($attributes);
            } else {
                $incident->fill($attributes)->save();
            }

            if (! filled($incident->tracking_number)) {
                $incident->tracking_number = $this->allocateTrackingNumber($incident);
                $incident->save();
            }

            $this->syncParties($incident, $payload['parties'] ?? null);

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::Submitted,
                'Incident formally submitted as '.$incident->tracking_number.'.',
                $user,
                oldValue: IncidentStatus::Draft->value,
                newValue: IncidentStatus::Submitted->value,
            );

            $this->notifySubmitted($incident->refresh());

            // MDM 10-02 / 10-15 — immutable Submitted milestone PDF snapshot.
            $this->retainedDocuments->retainIncidentSubmitted($incident, $user);

            return $incident;
        });
    }

    /**
     * Submit the active draft using its stored fields (Submit & Clock Out).
     */
    public function submitActiveDraft(User $user, Visit $visit): Incident
    {
        $draft = $this->activeDraftForVisit($visit);

        if ($draft === null) {
            throw ValidationException::withMessages([
                'incident_draft' => 'No unfinished Incident draft exists for this visit.',
            ]);
        }

        $payload = [
            'occurred_at' => $this->settings->datetimeLocalValue($draft->occurred_at),
            'original_category' => $draft->original_category,
            'original_subcategory' => $draft->original_subcategory,
            'summary' => $draft->summary,
            'immediate_action' => $draft->immediate_action,
            'injured' => $draft->injured,
            'injury_type' => $draft->injury_type,
            'injury_body_location' => $draft->injury_body_location,
            'injury_medical_check' => $draft->injury_medical_check,
            'injury_notes' => $draft->injury_notes,
            'injury_occurred_at' => $this->settings->datetimeLocalValue($draft->injury_occurred_at),
            'intervention' => $draft->intervention,
            'intervention_type' => $draft->intervention_type,
            'intervention_started_at' => $this->settings->datetimeLocalValue($draft->intervention_started_at),
            'intervention_stopped_at' => $this->settings->datetimeLocalValue($draft->intervention_stopped_at),
            'intervention_notes' => $draft->intervention_notes,
            'supervisor_notified' => $draft->supervisor_notified,
            'additional_notes' => $draft->additional_notes,
        ];

        foreach (['original_category', 'summary', 'immediate_action', 'occurred_at'] as $required) {
            if (! filled($payload[$required] ?? null)) {
                throw ValidationException::withMessages([
                    'incident_draft' => 'Complete required Incident fields before Submit & Clock Out.',
                ]);
            }
        }

        return $this->submit($user, $visit, $payload, $draft);
    }

    public function discardDraft(User $user, Incident $incident, ?string $reason = null): Incident
    {
        $employee = $user->employee;

        if ($employee === null || $incident->reporter_employee_id !== $employee->id) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $incident, $reason): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if (! $incident->isDraft()) {
                throw ValidationException::withMessages([
                    'incident' => 'Only an unfinished Incident draft may be discarded.',
                ]);
            }

            $incident->update([
                'discarded_at' => $this->settings->now(),
                'discarded_by_user_id' => $user->id,
            ]);

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::DraftDiscarded,
                'Incident draft discarded.',
                $user,
                reason: $reason,
                meta: [
                    'visit_id' => $incident->visit_id,
                    'dsp_employee_id' => $incident->reporter_employee_id,
                ],
            );

            return $incident->refresh();
        });
    }

    public function acknowledge(User $user, Incident $incident): Incident
    {
        return DB::transaction(function () use ($user, $incident): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if ($incident->status !== IncidentStatus::Submitted) {
                throw ValidationException::withMessages([
                    'incident' => 'Only a submitted Incident awaiting acknowledgment may be acknowledged.',
                ]);
            }

            if ($incident->isAcknowledged()) {
                return $incident;
            }

            $now = $this->settings->now();
            $incident->update([
                'acknowledged_at' => $now,
                'acknowledged_by_user_id' => $user->id,
                'status' => IncidentStatus::UnderReview,
            ]);

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::Acknowledged,
                'Management acknowledged responsibility for the submitted Incident.',
                $user,
                oldValue: IncidentStatus::Submitted->value,
                newValue: IncidentStatus::UnderReview->value,
                meta: [
                    'acknowledged_at' => $now->toIso8601String(),
                ],
            );

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::StatusChanged,
                'Lifecycle moved to Under review after acknowledgment.',
                $user,
                oldValue: IncidentStatus::Submitted->value,
                newValue: IncidentStatus::UnderReview->value,
            );

            return $incident->refresh();
        });
    }

    public function isOverdueForAcknowledgment(Incident $incident, ?CarbonInterface $now = null): bool
    {
        if ($incident->status !== IncidentStatus::Submitted || $incident->isAcknowledged() || $incident->submitted_at === null) {
            return false;
        }

        $deadline = $incident->submitted_at->copy()->addMinutes($this->acknowledgmentThresholdMinutes());

        return $this->settings->now($now)->greaterThan($deadline);
    }

    /**
     * Surface overdue acknowledgment through Operations/Admin attention and notify once.
     */
    public function escalateOverdueAcknowledgments(?CarbonInterface $now = null): int
    {
        $now = $this->settings->now($now);
        $threshold = $this->acknowledgmentThresholdMinutes();
        $count = 0;

        Incident::query()
            ->where('status', IncidentStatus::Submitted)
            ->whereNull('acknowledged_at')
            ->whereNull('acknowledgment_overdue_notified_at')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $now->copy()->subMinutes($threshold))
            ->orderBy('id')
            ->each(function (Incident $incident) use ($now, &$count): void {
                $this->markOverdueAndNotify($incident, $now);
                $count++;
            });

        return $count;
    }

    /**
     * @return Builder<Incident>
     */
    public function registerQueryFor(User $user): Builder
    {
        $query = Incident::query()->formal()->with([
            'client',
            'reporter',
            'responsibleSupervisor',
            'visit.scheduledVisit',
            'acknowledgedBy',
        ]);

        if ($user->isAdmin()) {
            return $query->orderByDesc('submitted_at')->orderByDesc('id');
        }

        if ($user->isSupervisor() && $user->employee) {
            $supervisorId = $user->employee->id;

            return $query
                ->where(function (Builder $builder) use ($supervisorId): void {
                    $builder
                        ->where('responsible_supervisor_id', $supervisorId)
                        ->orWhereHas('client', fn (Builder $client): Builder => $client->where('supervisor_id', $supervisorId));
                })
                ->orderByDesc('submitted_at')
                ->orderByDesc('id');
        }

        if ($user->isDsp() && $user->employee) {
            return $query
                ->where('reporter_employee_id', $user->employee->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id');
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attentionRowsFor(User $user): array
    {
        $this->escalateOverdueAcknowledgments();

        $rows = $this->registerQueryFor($user)
            ->whereIn('status', [IncidentStatus::Submitted->value, IncidentStatus::UnderReview->value])
            ->get();

        return $this->values($rows->map(fn (Incident $incident): array => $this->serializeListItem($incident, $user)));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForVisit(Visit $visit, ?User $viewer): array
    {
        $draft = null;
        $submitted = [];

        if ($viewer?->isDsp() && $viewer->employee?->id === $visit->employee_id) {
            $draftModel = $this->activeDraftForVisit($visit);
            $draft = $draftModel !== null ? $this->serializeDetail($draftModel, $viewer) : null;
        }

        $formal = Incident::query()
            ->formal()
            ->where('visit_id', $visit->id)
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn (Incident $incident): bool => $viewer !== null && $viewer->can('view', $incident));

        foreach ($formal as $incident) {
            $submitted[] = $this->serializeListItem($incident, $viewer);
        }

        $isReportingDsp = $viewer?->isDsp() && $viewer->employee?->id === $visit->employee_id;

        return [
            'draft' => $draft,
            'submitted' => $submitted,
            'categories' => $this->categoryOptions(),
            'acknowledgment_threshold_minutes' => $this->acknowledgmentThresholdMinutes(),
            'has_unresolved_draft' => $draft !== null,
            'party_clients' => $isReportingDsp ? $this->partyClientOptions($visit) : [],
            'party_employees' => $isReportingDsp ? $this->partyEmployeeOptions($visit) : [],
        ];
    }

    /**
     * Other Clients selectable as additional Incident parties (primary visit client is auto-added).
     *
     * @return list<array{id: int, name: string}>
     */
    private function partyClientOptions(Visit $visit): array
    {
        return $this->values(
            Client::query()
                ->where('status', ClientStatus::Active)
                ->where('id', '!=', $visit->client_id)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->full_name,
                ]),
        );
    }

    /**
     * Active operational employees selectable as additional Incident parties.
     *
     * @return list<array{id: int, name: string}>
     */
    private function partyEmployeeOptions(Visit $visit): array
    {
        return $this->values(
            Employee::query()
                ->operational()
                ->where('employment_status', EmploymentStatus::Active)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeListItem(Incident $incident, ?User $viewer = null): array
    {
        $overdue = $this->isOverdueForAcknowledgment($incident);

        return [
            'id' => $incident->id,
            'tracking_number' => $incident->tracking_number,
            'status' => $incident->status->value,
            'status_label' => $incident->status->label(),
            'original_category' => $incident->original_category,
            'original_subcategory' => $incident->original_subcategory,
            'official_category' => $incident->official_category,
            'official_subcategory' => $incident->official_subcategory,
            'summary' => $incident->summary,
            'occurred_at' => $incident->occurred_at?->toIso8601String(),
            'occurred_at_label' => $incident->occurred_at
                ? $this->settings->formatDateTime($incident->occurred_at)
                : null,
            'submitted_at' => $incident->submitted_at?->toIso8601String(),
            'submitted_at_label' => $incident->submitted_at
                ? $this->settings->formatDateTime($incident->submitted_at)
                : null,
            'acknowledged_at' => $incident->acknowledged_at?->toIso8601String(),
            'acknowledged_at_label' => $incident->acknowledged_at
                ? $this->settings->formatDateTime($incident->acknowledged_at)
                : null,
            'is_acknowledged' => $incident->isAcknowledged(),
            'requires_acknowledgment' => $incident->requiresAcknowledgment(),
            'overdue_for_acknowledgment' => $overdue,
            'acknowledgment_condition' => $this->acknowledgmentConditionLabel($incident, $overdue),
            'client' => [
                'id' => $incident->client_id,
                'name' => $incident->client?->full_name,
                'client_number' => $incident->client?->client_number,
            ],
            'reporter' => [
                'id' => $incident->reporter_employee_id,
                'name' => $incident->reporter?->full_name,
            ],
            'responsible_supervisor' => $incident->responsible_supervisor_id === null ? null : [
                'id' => $incident->responsible_supervisor_id,
                'name' => $incident->responsibleSupervisor?->full_name,
            ],
            'visit_id' => $incident->visit_id,
            'can_acknowledge' => $viewer?->can('acknowledge', $incident) ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDetail(Incident $incident, User $viewer): array
    {
        $incident->loadMissing([
            'client',
            'reporter',
            'responsibleSupervisor',
            'acknowledgedBy',
            'closedBy',
            'reopenedBy',
            'visit',
            'historyEvents.actor',
            'parties.client',
            'parties.employee',
            'reviews.reviewer',
            'followUps.assignee',
            'reportingRecords.reportedBy',
            'amendments.author',
        ]);

        $base = $this->serializeListItem($incident, $viewer);
        $management = $viewer->can('manage', $incident);
        $isReporter = $viewer->isDsp() && $viewer->employee?->id === $incident->reporter_employee_id;
        $workflow = app(IncidentWorkflowService::class);

        $detail = [
            ...$base,
            'immediate_action' => $incident->immediate_action,
            'injured' => $incident->injured,
            'injury_type' => $incident->injury_type,
            'injury_body_location' => $incident->injury_body_location,
            'injury_medical_check' => $incident->injury_medical_check,
            'injury_notes' => $incident->injury_notes,
            'injury_occurred_at' => $incident->injury_occurred_at?->toIso8601String(),
            'injury_occurred_at_label' => $incident->injury_occurred_at
                ? $this->settings->formatDateTime($incident->injury_occurred_at)
                : null,
            'intervention' => $incident->intervention,
            'intervention_type' => $incident->intervention_type,
            'intervention_started_at' => $incident->intervention_started_at?->toIso8601String(),
            'intervention_stopped_at' => $incident->intervention_stopped_at?->toIso8601String(),
            'intervention_notes' => $incident->intervention_notes,
            'supervisor_notified' => $incident->supervisor_notified,
            'additional_notes' => $incident->additional_notes,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'accuracy' => $incident->accuracy,
            'location_method' => $incident->location_method?->value,
            'location_status' => $incident->location_status?->value,
            'acknowledged_by_name' => $incident->acknowledgedBy?->name,
            'parties' => $this->serializeParties($incident),
            'amendments' => ($management || $isReporter)
                ? $this->values($incident->amendments->map(fn ($row): array => [
                    'id' => $row->id,
                    'field_key' => $row->field_key,
                    'reason' => $row->reason,
                    'original_content' => $row->original_content,
                    'new_content' => $row->new_content,
                    'author_name' => $row->author?->name,
                    'created_at_label' => $row->created_at
                        ? $this->settings->formatDateTime($row->created_at)
                        : null,
                ]))
                : [],
            'history' => $management || $isReporter
                ? $this->serializeHistory($incident, $viewer)
                : [],
            'is_read_only' => ! $incident->isDraft(),
            'management_layer_hidden' => ! $management,
            'closure_blockers' => $management ? $workflow->closureBlockers($incident) : [],
            'closed_at_label' => $incident->closed_at
                ? $this->settings->formatDateTime($incident->closed_at)
                : null,
            'closure_note' => $management ? $incident->closure_note : null,
            'closed_by_name' => $management ? $incident->closedBy?->name : null,
            'reopen_reason' => $management ? $incident->reopen_reason : null,
            'reopened_at_label' => $management && $incident->reopened_at
                ? $this->settings->formatDateTime($incident->reopened_at)
                : null,
            'documents' => [
                'submitted' => $incident->status->isFormal()
                    ? route('documents.incident-submitted.preview', $incident)
                    : null,
                'final' => $incident->status === IncidentStatus::Closed && $management
                    ? route('documents.incident-final.preview', $incident)
                    : null,
            ],
        ];

        if ($management) {
            $detail['finding'] = $incident->finding;
            $detail['cause_contributing_factors'] = $incident->cause_contributing_factors;
            $detail['prevention_plan'] = $incident->prevention_plan;
            $detail['reviews'] = $this->values($incident->reviews->map(fn ($row): array => [
                'id' => $row->id,
                'note' => $row->note,
                'reviewer_name' => $row->reviewer?->name,
                'created_at_label' => $row->created_at
                    ? $this->settings->formatDateTime($row->created_at)
                    : null,
            ]));
            $detail['follow_ups'] = $this->values($incident->followUps->map(fn ($row): array => [
                'id' => $row->id,
                'action_type' => $row->action_type,
                'assigned_employee_id' => $row->assigned_employee_id,
                'assignee_name' => $row->assignee?->full_name,
                'due_on' => $row->due_on?->toDateString(),
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'completion_note' => $row->completion_note,
                'completed_at_label' => $row->completed_at
                    ? $this->settings->formatDateTime($row->completed_at)
                    : null,
            ]));
            $detail['reporting_records'] = $this->values($incident->reportingRecords->map(fn ($row): array => [
                'id' => $row->id,
                'reported_to' => $row->reported_to,
                'person_organization' => $row->person_organization,
                'reported_at_label' => $row->reported_at
                    ? $this->settings->formatDateTime($row->reported_at)
                    : null,
                'reported_by_name' => $row->reportedBy?->full_name,
                'category' => $row->category,
                'method' => $row->method,
                'reference_number' => $row->reference_number,
                'note' => $row->note,
            ]));
        }

        return $detail;
    }

    /**
     * @param  list<array<string, mixed>>|null  $parties  null = leave additional parties unchanged
     */
    public function syncParties(Incident $incident, ?array $parties): void
    {
        if ($parties === null) {
            $hasPrimary = $incident->parties()
                ->where('party_kind', IncidentPartyKind::PrimaryClient)
                ->exists();

            if (! $hasPrimary) {
                IncidentParty::query()->create([
                    'incident_id' => $incident->id,
                    'party_kind' => IncidentPartyKind::PrimaryClient,
                    'client_id' => $incident->client_id,
                    'involvement' => 'Primary client context from the Active Visit',
                    'sort_order' => 0,
                ]);
            }

            return;
        }

        $incident->parties()->delete();

        IncidentParty::query()->create([
            'incident_id' => $incident->id,
            'party_kind' => IncidentPartyKind::PrimaryClient,
            'client_id' => $incident->client_id,
            'involvement' => 'Primary client context from the Active Visit',
            'sort_order' => 0,
        ]);

        $order = 1;
        foreach ($parties as $row) {
            if (! is_array($row)) {
                continue;
            }

            $kind = IncidentPartyKind::tryFrom((string) ($row['party_kind'] ?? ''));
            if ($kind === null || $kind === IncidentPartyKind::PrimaryClient) {
                continue;
            }

            IncidentParty::query()->create([
                'incident_id' => $incident->id,
                'party_kind' => $kind,
                'client_id' => $kind === IncidentPartyKind::Client ? ($row['client_id'] ?? null) : null,
                'employee_id' => $kind === IncidentPartyKind::Employee ? ($row['employee_id'] ?? null) : null,
                'name' => $kind === IncidentPartyKind::Outside ? $this->nullableString($row['name'] ?? null) : null,
                'organization' => $kind === IncidentPartyKind::Outside
                    ? $this->nullableString($row['organization'] ?? null)
                    : null,
                'involvement' => $this->nullableString($row['involvement'] ?? null),
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeParties(Incident $incident): array
    {
        return $this->values($incident->parties->map(function (IncidentParty $party): array {
            return [
                'id' => $party->id,
                'party_kind' => $party->party_kind->value,
                'party_kind_label' => $party->party_kind->label(),
                'client_id' => $party->client_id,
                'client_name' => $party->client?->full_name,
                'employee_id' => $party->employee_id,
                'employee_name' => $party->employee?->full_name,
                'name' => $party->name,
                'organization' => $party->organization,
                'involvement' => $party->involvement,
            ];
        }));
    }

    public function recordThresholdChange(User $user, int $oldMinutes, int $newMinutes): void
    {
        if ($oldMinutes === $newMinutes) {
            return;
        }

        IncidentHistoryEvent::query()->create([
            'incident_id' => null,
            'action' => IncidentHistoryAction::ThresholdConfigured,
            'summary' => 'Agency Incident acknowledgment threshold updated.',
            'old_value' => (string) $oldMinutes,
            'new_value' => (string) $newMinutes,
            'actor_user_id' => $user->id,
            'meta' => [
                'applies_prospectively' => true,
                'historical_acknowledgment_timestamps_preserved' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reporterAttributes(Visit $visit, array $payload): array
    {
        $visit->loadMissing(['client', 'scheduledVisit']);

        $injured = (bool) ($payload['injured'] ?? false);
        $intervention = (bool) ($payload['intervention'] ?? false);
        $occurredAt = $this->settings->parseLocalDateTime($payload['occurred_at'] ?? null)
            ?? $this->settings->now();

        return [
            'visit_id' => $visit->id,
            'client_id' => $visit->client_id,
            'reporter_employee_id' => $visit->employee_id,
            'responsible_supervisor_id' => $visit->client->supervisor_id
                ?? $visit->scheduledVisit?->supervisor_id,
            'occurred_at' => $occurredAt,
            'original_category' => $this->nullableString($payload['original_category'] ?? null),
            'original_subcategory' => $this->nullableString($payload['original_subcategory'] ?? null),
            'summary' => $this->nullableString($payload['summary'] ?? null),
            'immediate_action' => $this->nullableString($payload['immediate_action'] ?? null),
            'injured' => $injured,
            'injury_type' => $injured ? $this->nullableString($payload['injury_type'] ?? null) : null,
            'injury_body_location' => $injured ? $this->nullableString($payload['injury_body_location'] ?? null) : null,
            'injury_medical_check' => $injured ? $this->nullableString($payload['injury_medical_check'] ?? null) : null,
            'injury_notes' => $injured ? $this->nullableString($payload['injury_notes'] ?? null) : null,
            'injury_occurred_at' => $injured
                ? $this->settings->parseLocalDateTime($payload['injury_occurred_at'] ?? null)
                : null,
            'intervention' => $intervention,
            'intervention_type' => $intervention ? $this->nullableString($payload['intervention_type'] ?? null) : null,
            'intervention_started_at' => $intervention
                ? $this->settings->parseLocalDateTime($payload['intervention_started_at'] ?? null)
                : null,
            'intervention_stopped_at' => $intervention
                ? $this->settings->parseLocalDateTime($payload['intervention_stopped_at'] ?? null)
                : null,
            'intervention_notes' => $intervention ? $this->nullableString($payload['intervention_notes'] ?? null) : null,
            'supervisor_notified' => (bool) ($payload['supervisor_notified'] ?? false),
            'additional_notes' => $this->nullableString($payload['additional_notes'] ?? null),
            'latitude' => $payload['latitude'] ?? $visit->clock_in_latitude,
            'longitude' => $payload['longitude'] ?? $visit->clock_in_longitude,
            'accuracy' => $payload['accuracy'] ?? $visit->clock_in_accuracy,
            'location_method' => isset($payload['location_method'])
                ? ClockInLocationMethod::tryFrom((string) $payload['location_method'])
                : $visit->clock_in_location_method,
            'location_status' => isset($payload['location_status'])
                ? ClockInLocationStatus::tryFrom((string) $payload['location_status'])
                : $visit->clock_in_location_status,
        ];
    }

    private function allocateTrackingNumber(Incident $incident): string
    {
        $year = $this->settings->localNow()->format('Y');
        $sequence = str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT);

        return 'INC-'.$year.'-'.$sequence;
    }

    private function notifySubmitted(Incident $incident): void
    {
        $incident->loadMissing(['client', 'reporter', 'responsibleSupervisor.user']);
        $title = 'Critical Incident submitted';
        $body = ($incident->tracking_number ?? 'Incident').' for '
            .($incident->client?->full_name ?? 'client')
            .' requires management acknowledgment.';
        $url = route('incidents.show', $incident);
        $source = 'incident-submitted-'.$incident->id;

        $this->managementRecipients($incident)->each(
            function (User $recipient) use ($title, $body, $url, $source): void {
                $this->notifications->notify(
                    $recipient,
                    InAppNotificationType::Incident,
                    $title,
                    $body,
                    $source.'-user-'.$recipient->id,
                    $url,
                );
            },
        );
    }

    private function markOverdueAndNotify(Incident $incident, CarbonInterface $now): void
    {
        DB::transaction(function () use ($incident, $now): void {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if ($incident->acknowledgment_overdue_notified_at !== null || $incident->isAcknowledged()) {
                return;
            }

            $incident->update([
                'acknowledgment_overdue_notified_at' => $now,
            ]);

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::AcknowledgmentOverdue,
                'Incident became overdue for management acknowledgment.',
                null,
                meta: [
                    'threshold_minutes' => $this->acknowledgmentThresholdMinutes(),
                    'submitted_at' => $incident->submitted_at?->toIso8601String(),
                ],
            );

            $incident->loadMissing(['client', 'responsibleSupervisor.user']);
            $title = 'Incident overdue for acknowledgment';
            $body = ($incident->tracking_number ?? 'Incident').' has exceeded the Agency acknowledgment threshold.';
            $url = route('incidents.show', $incident);
            $source = 'incident-ack-overdue-'.$incident->id;

            $this->managementRecipients($incident)->each(
                function (User $recipient) use ($title, $body, $url, $source): void {
                    $this->notifications->notify(
                        $recipient,
                        InAppNotificationType::Incident,
                        $title,
                        $body,
                        $source.'-user-'.$recipient->id,
                        $url,
                    );
                },
            );
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function managementRecipients(Incident $incident): Collection
    {
        $recipients = collect();

        $supervisorUser = $incident->responsibleSupervisor?->user;
        if ($supervisorUser !== null) {
            $recipients->push($supervisorUser);
        }

        User::query()
            ->where('role', Role::Admin)
            ->get()
            ->each(fn (User $admin) => $recipients->push($admin));

        return $recipients->unique('id')->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeHistory(Incident $incident, User $viewer): array
    {
        $events = $incident->historyEvents;

        if ($viewer->isDsp()) {
            $events = $events->filter(fn (IncidentHistoryEvent $event): bool => in_array(
                $event->action,
                [
                    IncidentHistoryAction::DraftCreated,
                    IncidentHistoryAction::DraftUpdated,
                    IncidentHistoryAction::DraftDiscarded,
                    IncidentHistoryAction::Submitted,
                    IncidentHistoryAction::StatusChanged,
                ],
                true,
            ));
        }

        return $this->values($events->map(fn (IncidentHistoryEvent $event): array => [
            'id' => $event->id,
            'action' => $event->action->value,
            'action_label' => $event->action->label(),
            'summary' => $event->summary,
            'old_value' => $event->old_value,
            'new_value' => $event->new_value,
            'reason' => $event->reason,
            'actor_name' => $event->actor?->name,
            'created_at' => $event->created_at?->toIso8601String(),
            'created_at_label' => $event->created_at
                ? $this->settings->formatDateTime($event->created_at)
                : null,
        ]));
    }

    private function acknowledgmentConditionLabel(Incident $incident, bool $overdue): string
    {
        if ($incident->status === IncidentStatus::Draft) {
            return 'Draft';
        }

        if ($incident->isAcknowledged()) {
            return 'Acknowledged';
        }

        if ($overdue) {
            return 'Overdue for acknowledgment';
        }

        if ($incident->requiresAcknowledgment()) {
            return 'Awaiting acknowledgment';
        }

        return 'Not applicable';
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function recordHistory(
        Incident $incident,
        IncidentHistoryAction $action,
        string $summary,
        ?User $actor,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $reason = null,
        ?array $meta = null,
    ): IncidentHistoryEvent {
        return IncidentHistoryEvent::query()->create([
            'incident_id' => $incident->id,
            'action' => $action,
            'summary' => $summary,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
            'meta' => $meta,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>|iterable<T>  $items
     * @return list<T>
     */
    private function values(iterable $items): array
    {
        return array_values($items instanceof Collection ? $items->all() : iterator_to_array($items));
    }
}
