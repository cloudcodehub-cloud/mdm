<?php

namespace App\Services;

use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitAssignmentKind;
use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MDM 05 §10.6–10.7 / MDM 04-08 — mid-service replacement never transfers an
 * Active Visit. The outgoing segment is administratively ended (no fabricated
 * DSP clock-out), assignment history records the replacement, and the incoming
 * DSP later clocks in to create a linked Visit segment.
 */
class VisitHandoffService
{
    public function __construct(
        private SettingsService $settings,
        private VisitAdministrativeCloseService $adminClose,
        private VisitAssignmentService $assignments,
        private ScheduledVisitService $scheduling,
        private ComplianceEligibilityService $complianceEligibility,
    ) {}

    /**
     * @param  array{
     *     employee_id: int,
     *     effective_ended_at: string,
     *     reason: string,
     *     override_reason?: string|null,
     *     mark_call_off?: bool
     * }  $payload
     */
    public function handOff(User $actor, Visit $outgoing, array $payload): Visit
    {
        return DB::transaction(function () use ($actor, $outgoing, $payload): Visit {
            /** @var Visit $outgoing */
            $outgoing = Visit::query()
                ->with(['scheduledVisit.client', 'employee'])
                ->whereKey($outgoing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $outgoing->isInProgress()) {
                throw ValidationException::withMessages([
                    'visit' => 'Only an active visit can be handed off.',
                ]);
            }

            $scheduled = $outgoing->scheduledVisit;
            $scheduled = $scheduled->newQuery()->whereKey($scheduled->id)->lockForUpdate()->firstOrFail();

            if ($scheduled->status === ScheduledVisitStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'visit' => 'Cancelled scheduled visits cannot be handed off.',
                ]);
            }

            $replacement = Employee::query()->findOrFail((int) $payload['employee_id']);

            if ($replacement->id === $outgoing->employee_id) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Select a different DSP for the handoff.',
                ]);
            }

            if (! $replacement->isActiveDsp()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'The replacement must be an active DSP.',
                ]);
            }

            $now = $this->settings->now();
            $complianceBlock = $this->complianceEligibility->serviceBlockReason($replacement, $now);
            if ($complianceBlock !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => $complianceBlock,
                ]);
            }

            // Use Scheduled status so eligibility / availability hard blocks still run.
            $schedulablePayload = [
                'client_id' => $scheduled->client_id,
                'employee_id' => $replacement->id,
                'supervisor_id' => $scheduled->supervisor_id,
                'shift_template_id' => $scheduled->shift_template_id,
                'service_date' => $scheduled->service_date->toDateString(),
                'starts_at' => $scheduled->starts_at,
                'ends_at' => $scheduled->ends_at,
                'service_type' => $scheduled->service_type,
                'status' => ScheduledVisitStatus::Scheduled->value,
                'override_reason' => isset($payload['override_reason'])
                    ? trim((string) $payload['override_reason'])
                    : null,
            ];

            $conflict = $this->scheduling->assertSchedulable($actor, $schedulablePayload, $scheduled);

            $reason = trim((string) $payload['reason']);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Enter a handoff reason.',
                ]);
            }

            // End outgoing segment without fabricating DSP EVV clock-out/GPS.
            $closed = $this->adminClose->closeSegment(
                $actor,
                $outgoing,
                [
                    'effective_ended_at' => $payload['effective_ended_at'],
                    'reason' => $reason,
                ],
                completeScheduled: false,
                useTransaction: false,
            );

            $this->assignments->reassign(
                $scheduled->fresh(['shiftTemplate']) ?? $scheduled,
                $replacement,
                $actor,
                $reason,
                VisitAssignmentKind::Replacement,
                (bool) ($payload['mark_call_off'] ?? false),
                $conflict !== null ? ($schedulablePayload['override_reason'] ?? null) : null,
            );

            $scheduled->refresh();
            $scheduled->update([
                'status' => ScheduledVisitStatus::InProgress,
                'handoff_pending' => true,
                'updated_by_user_id' => $actor->id,
            ]);

            $closed->update([
                'handed_off_at' => $this->settings->now(),
                'handed_off_by_user_id' => $actor->id,
                'handoff_reason' => $reason,
            ]);

            return $closed->refresh();
        });
    }
}
