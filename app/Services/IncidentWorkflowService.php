<?php

namespace App\Services;

use App\Enums\IncidentFollowUpStatus;
use App\Enums\IncidentHistoryAction;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAmendment;
use App\Models\IncidentFollowUp;
use App\Models\IncidentHistoryEvent;
use App\Models\IncidentReportingRecord;
use App\Models\IncidentReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentWorkflowService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Amendable reporter fields. Original submission values stay on the Incident row
     * until an Amendment records the prior content and applies the new value.
     *
     * @var list<string>
     */
    public const AMENDABLE_FIELDS = [
        'summary',
        'immediate_action',
        'additional_notes',
        'original_category',
        'original_subcategory',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOfficialClassification(User $user, Incident $incident, array $payload): Incident
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($incident);

            $old = trim(($incident->official_category ?? '').'/'.($incident->official_subcategory ?? ''), '/');
            $category = trim((string) $payload['official_category']);
            $subcategory = $this->nullableString($payload['official_subcategory'] ?? null);

            $incident->update([
                'official_category' => $category,
                'official_subcategory' => $subcategory,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::OfficialClassificationUpdated,
                'Official Category/Subcategory updated.',
                $user,
                oldValue: $old !== '' ? $old : null,
                newValue: trim($category.'/'.($subcategory ?? ''), '/'),
            );

            return $incident->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addReview(User $user, Incident $incident, array $payload): IncidentReview
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): IncidentReview {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($incident);

            if ($incident->status === IncidentStatus::Submitted && ! $incident->isAcknowledged()) {
                throw ValidationException::withMessages([
                    'incident' => 'Acknowledge the Incident before recording management review.',
                ]);
            }

            $review = IncidentReview::query()->create([
                'incident_id' => $incident->id,
                'reviewer_user_id' => $user->id,
                'note' => trim((string) $payload['note']),
                'determinations' => $payload['determinations'] ?? null,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::ReviewAdded,
                'Management review entry recorded.',
                $user,
                newValue: $review->note,
            );

            return $review;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateInvestigation(User $user, Incident $incident, array $payload): Incident
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($incident);

            $incident->update([
                'finding' => array_key_exists('finding', $payload)
                    ? $this->nullableString($payload['finding'])
                    : $incident->finding,
                'cause_contributing_factors' => array_key_exists('cause_contributing_factors', $payload)
                    ? $this->nullableString($payload['cause_contributing_factors'])
                    : $incident->cause_contributing_factors,
                'prevention_plan' => array_key_exists('prevention_plan', $payload)
                    ? $this->nullableString($payload['prevention_plan'])
                    : $incident->prevention_plan,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::InvestigationUpdated,
                'Finding / cause / prevention plan updated.',
                $user,
            );

            return $incident->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFollowUp(User $user, Incident $incident, array $payload): IncidentFollowUp
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): IncidentFollowUp {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($incident);

            $followUp = IncidentFollowUp::query()->create([
                'incident_id' => $incident->id,
                'action_type' => trim((string) $payload['action_type']),
                'assigned_employee_id' => (int) $payload['assigned_employee_id'],
                'due_on' => $payload['due_on'] ?? null,
                'status' => IncidentFollowUpStatus::Open,
                'created_by_user_id' => $user->id,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::FollowUpCreated,
                'Follow-up assigned: '.$followUp->action_type,
                $user,
                meta: ['follow_up_id' => $followUp->id],
            );

            return $followUp;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function completeFollowUp(User $user, IncidentFollowUp $followUp, array $payload): IncidentFollowUp
    {
        $followUp->loadMissing('incident');
        $this->assertManageable($followUp->incident);

        return DB::transaction(function () use ($user, $followUp, $payload): IncidentFollowUp {
            /** @var IncidentFollowUp $followUp */
            $followUp = IncidentFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if (! $followUp->isOpen()) {
                return $followUp;
            }

            $followUp->update([
                'status' => IncidentFollowUpStatus::Completed,
                'completed_at' => $this->settings->now(),
                'completed_by_user_id' => $user->id,
                'completion_note' => $this->nullableString($payload['completion_note'] ?? null),
            ]);

            $this->history(
                $followUp->incident,
                IncidentHistoryAction::FollowUpCompleted,
                'Follow-up completed: '.$followUp->action_type,
                $user,
                meta: ['follow_up_id' => $followUp->id],
            );

            return $followUp->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reassignFollowUp(User $user, IncidentFollowUp $followUp, array $payload): IncidentFollowUp
    {
        $followUp->loadMissing('incident');
        $this->assertManageable($followUp->incident);

        return DB::transaction(function () use ($user, $followUp, $payload): IncidentFollowUp {
            /** @var IncidentFollowUp $followUp */
            $followUp = IncidentFollowUp::query()->whereKey($followUp->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($followUp->incident);

            if (! $followUp->isOpen()) {
                throw ValidationException::withMessages([
                    'follow_up' => 'Only open Follow-Ups may be reassigned.',
                ]);
            }

            $previous = $followUp->assigned_employee_id;
            $followUp->update([
                'assigned_employee_id' => (int) $payload['assigned_employee_id'],
            ]);

            $this->history(
                $followUp->incident,
                IncidentHistoryAction::FollowUpReassigned,
                'Follow-up reassigned.',
                $user,
                oldValue: (string) $previous,
                newValue: (string) $followUp->assigned_employee_id,
                meta: ['follow_up_id' => $followUp->id],
            );

            return $followUp->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addReportingRecord(User $user, Incident $incident, array $payload): IncidentReportingRecord
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): IncidentReportingRecord {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $this->assertNotClosed($incident);

            $record = IncidentReportingRecord::query()->create([
                'incident_id' => $incident->id,
                'reported_to' => trim((string) $payload['reported_to']),
                'person_organization' => $this->nullableString($payload['person_organization'] ?? null),
                'reported_at' => $this->settings->parseLocalDateTime($payload['reported_at'] ?? null)
                    ?? $this->settings->now(),
                'reported_by_employee_id' => $payload['reported_by_employee_id'] ?? $user->employee?->id,
                'category' => $this->nullableString($payload['category'] ?? null),
                'method' => $this->nullableString($payload['method'] ?? null),
                'reference_number' => $this->nullableString($payload['reference_number'] ?? null),
                'note' => $this->nullableString($payload['note'] ?? null),
                'created_by_user_id' => $user->id,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::ReportingRecordAdded,
                'Formal reporting record added for '.$record->reported_to.'.',
                $user,
                meta: ['reporting_record_id' => $record->id],
            );

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addAmendment(User $user, Incident $incident, array $payload): IncidentAmendment
    {
        return DB::transaction(function () use ($user, $incident, $payload): IncidentAmendment {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if (! $incident->status->isFormal()) {
                throw ValidationException::withMessages([
                    'incident' => 'Amendments apply only after formal submission.',
                ]);
            }

            $this->assertNotClosed($incident);

            $field = (string) $payload['field_key'];
            if (! in_array($field, self::AMENDABLE_FIELDS, true)) {
                throw ValidationException::withMessages([
                    'field_key' => 'That field cannot be amended.',
                ]);
            }

            $isReporter = $user->isDsp()
                && $user->employee !== null
                && $incident->reporter_employee_id === $user->employee->id;
            $isManager = $user->can('manage', $incident);

            if (! $isReporter && ! $isManager) {
                abort(403);
            }

            $original = $incident->{$field};
            $newContent = trim((string) $payload['new_content']);

            $amendment = IncidentAmendment::query()->create([
                'incident_id' => $incident->id,
                'author_user_id' => $user->id,
                'reason' => trim((string) $payload['reason']),
                'field_key' => $field,
                'original_content' => is_string($original) ? $original : null,
                'new_content' => $newContent,
            ]);

            // Apply correction to current working fields while preserving amendment history.
            $incident->update([$field => $newContent]);

            $this->history(
                $incident,
                IncidentHistoryAction::AmendmentAdded,
                'Amendment recorded for '.$field.'.',
                $user,
                oldValue: is_string($original) ? $original : null,
                newValue: $newContent,
                reason: $amendment->reason,
                meta: ['amendment_id' => $amendment->id, 'field_key' => $field],
            );

            return $amendment;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function close(User $user, Incident $incident, array $payload): Incident
    {
        $this->assertManageable($incident);

        return DB::transaction(function () use ($user, $incident, $payload): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()
                ->with(['reviews', 'followUps'])
                ->whereKey($incident->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($incident->status === IncidentStatus::Closed) {
                return $incident;
            }

            if ($incident->status === IncidentStatus::Draft) {
                throw ValidationException::withMessages([
                    'incident' => 'Draft Incidents cannot be closed.',
                ]);
            }

            $blockers = $this->closureBlockers($incident);
            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'closure' => $blockers[0],
                ]);
            }

            $note = trim((string) $payload['closure_note']);
            $now = $this->settings->now();
            $previous = $incident->status->value;

            $incident->update([
                'status' => IncidentStatus::Closed,
                'closed_at' => $now,
                'closed_by_user_id' => $user->id,
                'closure_note' => $note,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::Closed,
                'Incident closed.',
                $user,
                oldValue: $previous,
                newValue: IncidentStatus::Closed->value,
                reason: $note,
            );

            $this->history(
                $incident,
                IncidentHistoryAction::StatusChanged,
                'Lifecycle moved to Closed.',
                $user,
                oldValue: $previous,
                newValue: IncidentStatus::Closed->value,
            );

            return $incident->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reopen(User $user, Incident $incident, array $payload): Incident
    {
        if (! $user->isAdmin()) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $incident, $payload): Incident {
            /** @var Incident $incident */
            $incident = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if ($incident->status !== IncidentStatus::Closed) {
                throw ValidationException::withMessages([
                    'incident' => 'Only a CLOSED Incident may be reopened.',
                ]);
            }

            $reason = trim((string) $payload['reopen_reason']);
            $now = $this->settings->now();

            $incident->update([
                'original_closed_at' => $incident->original_closed_at ?? $incident->closed_at,
                'original_closed_by_user_id' => $incident->original_closed_by_user_id ?? $incident->closed_by_user_id,
                'original_closure_note' => $incident->original_closure_note ?? $incident->closure_note,
                'status' => IncidentStatus::UnderReview,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'closure_note' => null,
                'reopened_at' => $now,
                'reopened_by_user_id' => $user->id,
                'reopen_reason' => $reason,
            ]);

            $this->history(
                $incident,
                IncidentHistoryAction::Reopened,
                'CLOSED Incident reopened by Admin.',
                $user,
                oldValue: IncidentStatus::Closed->value,
                newValue: IncidentStatus::UnderReview->value,
                reason: $reason,
            );

            $this->history(
                $incident,
                IncidentHistoryAction::StatusChanged,
                'Lifecycle returned to Under review.',
                $user,
                oldValue: IncidentStatus::Closed->value,
                newValue: IncidentStatus::UnderReview->value,
            );

            return $incident->refresh();
        });
    }

    /**
     * @return list<string>
     */
    public function closureBlockers(Incident $incident): array
    {
        $incident->loadMissing(['reviews', 'followUps']);
        $blockers = [];

        if (! filled($incident->official_category)) {
            $blockers[] = 'Confirm Official Category before closure.';
        }

        if ($incident->reviews->isEmpty()) {
            $blockers[] = 'Record at least one management review before closure.';
        }

        if (! filled($incident->finding)) {
            $blockers[] = 'Complete Finding before closure.';
        }

        if (! filled($incident->cause_contributing_factors)) {
            $blockers[] = 'Complete Cause & Contributing Factors before closure.';
        }

        if (! filled($incident->prevention_plan)) {
            $blockers[] = 'Complete Prevention Plan before closure.';
        }

        $openFollowUps = $incident->followUps->filter(fn (IncidentFollowUp $row): bool => $row->isOpen());
        if ($openFollowUps->isNotEmpty()) {
            $blockers[] = 'Complete or resolve required Follow-Ups before closure.';
        }

        return $blockers;
    }

    private function assertManageable(Incident $incident): void
    {
        if ($incident->status === IncidentStatus::Draft) {
            throw ValidationException::withMessages([
                'incident' => 'Draft Incidents are not in the management workflow.',
            ]);
        }
    }

    private function assertNotClosed(Incident $incident): void
    {
        if ($incident->status === IncidentStatus::Closed) {
            throw ValidationException::withMessages([
                'incident' => 'Reopen the CLOSED Incident before changing management records.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function history(
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
}
