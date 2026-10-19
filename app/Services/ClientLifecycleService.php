<?php

namespace App\Services;

use App\Enums\ClientAdminHistoryCategory;
use App\Enums\ClientStatus;
use App\Enums\Role;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientLifecycleService
{
    public function __construct(
        private SettingsService $settings,
        private ClientAdminHistoryService $history,
        private SchedulingNotificationService $notifications,
    ) {}

    /**
     * MDM 03 Archive Pending: an in-progress visit blocks safe archival of a Client.
     *
     * @return list<array{type: string, label: string, count: int, visit_id?: int}>
     */
    public function archiveBlockers(Client $client): array
    {
        $activeVisit = Visit::query()
            ->where('client_id', $client->id)
            ->where('status', VisitStatus::InProgress)
            ->with('employee')
            ->first();

        if ($activeVisit === null) {
            return [];
        }

        return [[
            'type' => 'active_visit',
            'label' => 'Active visit'.($activeVisit->employee !== null ? ' with '.$activeVisit->employee->full_name : '').' must finish',
            'count' => 1,
            'visit_id' => $activeVisit->id,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function impactPreview(Client $client, ClientStatus $target, ?string $effectiveOn = null): array
    {
        // Archive Pending is applied automatically by an Archive request; preview it as Archive.
        if ($target === ClientStatus::ArchivePending) {
            $target = ClientStatus::Archived;
        }

        $today = $this->settings->today();
        $effective = $effectiveOn !== null && $effectiveOn !== ''
            ? Carbon::parse($effectiveOn)->toDateString()
            : $today;

        $futureVisits = $client->scheduledVisits()
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $effective)
            ->with(['employee', 'shiftTemplate'])
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $activeVisit = Visit::query()
            ->where('client_id', $client->id)
            ->where('status', VisitStatus::InProgress)
            ->with('employee')
            ->first();

        $services = $client->careServices()
            ->orderBy('name')
            ->get(['care_services.id', 'care_services.name'])
            ->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
            ])
            ->values()
            ->all();

        $archiveBlockers = $target === ClientStatus::Archived && ! $client->isArchivePending()
            ? $this->archiveBlockers($client)
            : [];
        $willEnterArchivePending = $archiveBlockers !== [];

        $consequences = match ($target) {
            ClientStatus::Paused => [
                'Future scheduling is blocked while the client remains paused.',
                'Existing future Scheduled Visits remain listed for Scheduling/Operations resolution and are not silently deleted.',
                'Returning the client to Active restores prospective eligibility only.',
            ],
            ClientStatus::Discharged => [
                'Future scheduling is blocked after discharge/service end.',
                'Existing future Scheduled Visits remain listed for Scheduling/Operations resolution and are not silently deleted.',
                'Historical visits, Care Plans, and attendance remain intact.',
                'A later return reuses this client record; new service starts prospectively.',
            ],
            ClientStatus::Archived, ClientStatus::ArchivePending => $willEnterArchivePending
                ? [
                    'An active visit blocks safe archival, so this client will enter Archive Pending. The archive request is accepted now; you do not need to repeat it.',
                    'The active visit continues unchanged. Archiving completes automatically after the visit is completed.',
                    'Future scheduled visits are cancelled when you confirm; no new scheduling is allowed while Archive Pending.',
                    'Historical records remain preserved.',
                ]
                : [
                    'Archive is an administrative/retention state.',
                    'Future scheduled visits are cancelled; the client is removed from ordinary active operations.',
                    'Historical records remain preserved.',
                ],
            ClientStatus::Active => [
                'Prospective eligibility is restored only if service/authorization dates permit.',
                'Historical or cancelled visits are not automatically recreated.',
            ],
        };

        return [
            'client_id' => $client->id,
            'current_status' => $client->status->value,
            'current_status_label' => $client->status->label(),
            'target_status' => $target->value,
            'target_status_label' => $target->label(),
            'effective_on' => $effective,
            'affected_services' => $services,
            'future_scheduled_visits' => $futureVisits->map(function ($visit): array {
                return [
                    'id' => $visit->id,
                    'service_date' => $visit->service_date->toDateString(),
                    'service_type' => $visit->service_type,
                    'dsp_name' => $visit->employee?->full_name,
                    'time_label' => $this->settings->formatTime($visit->startsAtOn()).' – '.$this->settings->formatTime($visit->endsAtOn()),
                ];
            })->values()->all(),
            'future_scheduled_count' => $futureVisits->count(),
            'will_cancel_future_visits' => $target === ClientStatus::Archived
                && ! $client->isArchivePending()
                && $futureVisits->isNotEmpty(),
            'will_enter_archive_pending' => $willEnterArchivePending,
            'archive_blockers' => $archiveBlockers,
            'active_visit' => $activeVisit === null ? null : [
                'id' => $activeVisit->id,
                'dsp_name' => $activeVisit->employee?->full_name,
                'warning' => 'An active visit is in progress. Lifecycle change does not automatically end or invalidate that visit.',
            ],
            'consequences' => $consequences,
        ];
    }

    /**
     * @param  array{status: string, reason?: string|null, effective_on?: string|null}  $payload
     */
    public function transition(Client $client, User $actor, array $payload, ?CarbonInterface $now = null): Client
    {
        $target = ClientStatus::from($payload['status']);
        $now = $now ?? $this->settings->now();

        if ($target === ClientStatus::ArchivePending) {
            throw ValidationException::withMessages([
                'status' => 'Archive Pending is applied automatically when an Admin archives a client with an active visit. Choose Archived.',
            ]);
        }

        if ($target === $client->status) {
            return $client;
        }

        if ($target === ClientStatus::Archived && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'status' => 'Only administrators may archive clients.',
            ]);
        }

        if ($client->status === ClientStatus::Archived && $target !== ClientStatus::Active && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'status' => 'Archived clients can only be restored to Active by an administrator.',
            ]);
        }

        if ($client->isArchivePending()) {
            if ($target === ClientStatus::Archived) {
                // Already accepted. Finalize only when the active-visit blocker is gone.
            } elseif ($actor->isAdmin()) {
                // MDM 03 §25: no Cancel Pending / undo transition — wait for auto-finalize.
                throw ValidationException::withMessages([
                    'status' => 'This client has a pending archive. Archiving completes automatically after the active visit ends; the request cannot be cancelled.',
                ]);
            } else {
                throw ValidationException::withMessages([
                    'status' => 'Only administrators may change a client with a pending archive.',
                ]);
            }
        }

        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;
        $reason = $reason === '' ? null : $reason;

        if (in_array($target, [ClientStatus::Paused, ClientStatus::Discharged, ClientStatus::Archived], true) && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Provide a reason for this lifecycle change.',
            ]);
        }

        $effectiveOn = isset($payload['effective_on']) && filled($payload['effective_on'])
            ? Carbon::parse((string) $payload['effective_on'])->toDateString()
            : $this->settings->today();

        if ($client->isArchivePending() && $target === ClientStatus::Archived) {
            // Already accepted. Admin does not repeat: finalize only when the visit has ended.
            $this->finalizeArchivePending($client, $actor, $now);

            return $client->refresh();
        }

        return DB::transaction(function () use ($client, $actor, $target, $reason, $now, $effectiveOn): Client {
            $previous = $client->status;

            if ($target === ClientStatus::Archived) {
                $blockers = $this->archiveBlockers($client);

                // Admin confirmed archive: future visits are cancelled now; the active visit is untouched.
                $cancelled = $this->cancelFutureVisits($client, $actor, $reason, $effectiveOn);

                if ($blockers !== []) {
                    $client->forceFill([
                        'status' => ClientStatus::ArchivePending,
                        'status_reason' => $reason,
                        'status_changed_at' => $now,
                        'status_changed_by_user_id' => $actor->id,
                        'archive_pending_at' => $now,
                        'archive_pending_by_user_id' => $actor->id,
                        'archive_pending_reason' => $reason,
                        'archive_pending_blockers' => $blockers,
                        'archive_pending_from_status' => $previous->value,
                    ])->save();

                    $this->history->record($client, $actor, [
                        'category' => ClientAdminHistoryCategory::Lifecycle,
                        'summary' => 'Archive requested — Archive Pending until the active visit ends',
                        'old_value' => $previous->label(),
                        'new_value' => ClientStatus::ArchivePending->label(),
                        'reason' => $reason,
                        'effective_on' => $effectiveOn,
                        'subject' => $client,
                        'meta' => [
                            'blockers' => $blockers,
                            'from_status' => $previous->value,
                            'cancelled_future_visits' => $cancelled,
                        ],
                    ]);

                    return $client->refresh();
                }
            }

            $client->forceFill([
                'status' => $target,
                'status_reason' => $reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
            ])->save();

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Lifecycle,
                'summary' => 'Client lifecycle changed to '.$target->label(),
                'old_value' => $previous->label(),
                'new_value' => $target->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $client,
            ]);

            return $client->refresh();
        });
    }

    /**
     * Complete a pending archive once the active visit has ended. Safe to call repeatedly.
     * Returns true only when the client became Archived by this call.
     */
    public function finalizeArchivePending(Client $client, ?User $actor = null, ?CarbonInterface $now = null): bool
    {
        $now = $now ?? $this->settings->now();

        return DB::transaction(function () use ($client, $actor, $now): bool {
            /** @var Client|null $locked */
            $locked = Client::query()->whereKey($client->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isArchivePending()) {
                return false;
            }

            if ($this->archiveBlockers($locked) !== []) {
                return false;
            }

            $actor = $actor
                ?? $locked->archivePendingBy
                ?? User::query()->where('role', Role::Admin)->orderBy('id')->first();

            if ($actor === null) {
                return false;
            }

            $reason = $locked->archive_pending_reason ?? $locked->status_reason ?? 'Archive Pending completed';
            $effectiveOn = $this->settings->today();

            // Safety net: nothing may remain scheduled for an archived client.
            $cancelled = $this->cancelFutureVisits($locked, $actor, $reason, $effectiveOn);

            $locked->forceFill([
                'status' => ClientStatus::Archived,
                'status_reason' => $reason,
                'status_changed_at' => $now,
                'status_changed_by_user_id' => $actor->id,
                ...$this->clearedArchivePendingAttributes(),
            ])->save();

            $this->history->record($locked, $actor, [
                'category' => ClientAdminHistoryCategory::Lifecycle,
                'summary' => 'Archive Pending completed — client Archived automatically',
                'old_value' => ClientStatus::ArchivePending->label(),
                'new_value' => ClientStatus::Archived->label(),
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'subject' => $locked,
                'meta' => ['cancelled_future_visits' => $cancelled],
            ]);

            return true;
        });
    }

    /**
     * @return array<string, null>
     */
    private function clearedArchivePendingAttributes(): array
    {
        return [
            'archive_pending_at' => null,
            'archive_pending_by_user_id' => null,
            'archive_pending_reason' => null,
            'archive_pending_blockers' => null,
            'archive_pending_from_status' => null,
        ];
    }

    /**
     * Cancel Scheduled visits from the effective date onward. In-progress and historical visits are untouched.
     */
    private function cancelFutureVisits(Client $client, User $actor, ?string $reason, string $effectiveOn): int
    {
        $visits = ScheduledVisit::query()
            ->where('client_id', $client->id)
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $effectiveOn)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        foreach ($visits as $visit) {
            $visit->forceFill([
                'status' => ScheduledVisitStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => 'Client archived'.($reason ? ' — '.$reason : ''),
                'staffing_needed' => false,
                'staffing_urgency_escalated_at' => null,
                'needs_attention' => false,
                'attention_reason' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            if ($visit->employee_id !== null) {
                $this->notifications->visitCancelled($visit);
            }
        }

        if ($visits->isNotEmpty()) {
            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Lifecycle,
                'summary' => $visits->count().' future scheduled visit(s) cancelled by client archive',
                'old_value' => (string) $visits->count(),
                'new_value' => 'cancelled',
                'reason' => $reason,
                'effective_on' => $effectiveOn,
                'meta' => ['visit_ids' => $visits->pluck('id')->all()],
            ]);
        }

        return $visits->count();
    }
}
