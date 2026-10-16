<?php

namespace App\Services;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\InAppNotificationType;
use App\Enums\IncidentHistoryAction;
use App\Enums\IncidentStatus;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\IncidentHistoryEvent;
use App\Models\User;
use App\Models\Visit;
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

            $this->recordHistory(
                $incident,
                IncidentHistoryAction::Submitted,
                'Incident formally submitted as '.$incident->tracking_number.'.',
                $user,
                oldValue: IncidentStatus::Draft->value,
                newValue: IncidentStatus::Submitted->value,
            );

            $this->notifySubmitted($incident->refresh());

            return $incident;
        });
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

        return [
            'draft' => $draft,
            'submitted' => $submitted,
            'categories' => $this->categoryOptions(),
            'acknowledgment_threshold_minutes' => $this->acknowledgmentThresholdMinutes(),
            'has_unresolved_draft' => $draft !== null,
        ];
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
            'visit',
            'historyEvents.actor',
        ]);

        $base = $this->serializeListItem($incident, $viewer);
        $management = $viewer->isAdmin() || $viewer->isSupervisor();

        return [
            ...$base,
            'immediate_action' => $incident->immediate_action,
            'injured' => $incident->injured,
            'injury_type' => $incident->injury_type,
            'injury_body_location' => $incident->injury_body_location,
            'injury_medical_check' => $incident->injury_medical_check,
            'injury_notes' => $incident->injury_notes,
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
            'history' => $management || ($viewer->isDsp() && $viewer->employee?->id === $incident->reporter_employee_id)
                ? $this->serializeHistory($incident, $viewer)
                : [],
            'is_read_only' => ! $incident->isDraft(),
            'management_layer_hidden' => $viewer->isDsp(),
        ];
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
