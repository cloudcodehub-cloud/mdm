<?php

namespace App\Services;

use App\Enums\ClientAdminHistoryCategory;
use App\Enums\ClientStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function impactPreview(Client $client, ClientStatus $target, ?string $effectiveOn = null): array
    {
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
            ClientStatus::Archived => [
                'Archive is an administrative/retention state.',
                'The client is removed from ordinary active operations.',
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

        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;
        $reason = $reason === '' ? null : $reason;

        if (in_array($target, [ClientStatus::Paused, ClientStatus::Discharged], true) && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'Provide a reason for this lifecycle change.',
            ]);
        }

        return DB::transaction(function () use ($client, $actor, $target, $reason, $now, $payload): Client {
            $previous = $client->status;
            $effectiveOn = isset($payload['effective_on']) && filled($payload['effective_on'])
                ? Carbon::parse((string) $payload['effective_on'])->toDateString()
                : $this->settings->today();

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
}
