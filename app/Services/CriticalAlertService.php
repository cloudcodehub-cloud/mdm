<?php

namespace App\Services;

use App\Enums\CriticalAlertCategory;
use App\Enums\InAppNotificationType;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\ClientCriticalAlert;
use App\Models\ClientCriticalAlertAcknowledgment;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CriticalAlertService
{
    public function __construct(
        private SettingsService $settings,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * @param  array{category: string, title: string, body: string, is_urgent?: bool}  $payload
     */
    public function create(Client $client, User $actor, array $payload): ClientCriticalAlert
    {
        $alert = ClientCriticalAlert::query()->create([
            'client_id' => $client->id,
            'category' => CriticalAlertCategory::from($payload['category']),
            'title' => trim($payload['title']),
            'body' => trim($payload['body']),
            'version' => 1,
            'is_urgent' => (bool) ($payload['is_urgent'] ?? false),
            'is_active' => true,
            'created_by_user_id' => $actor->id,
        ]);

        $this->notifyActiveVisitDsps($alert);

        return $alert->refresh();
    }

    /**
     * Material content changes bump version so DSPs must re-acknowledge.
     *
     * @param  array{category?: string, title?: string, body?: string, is_urgent?: bool}  $payload
     */
    public function update(ClientCriticalAlert $alert, User $actor, array $payload): ClientCriticalAlert
    {
        if (! $alert->is_active) {
            throw ValidationException::withMessages([
                'alert' => 'Inactive critical alerts cannot be edited.',
            ]);
        }

        $category = isset($payload['category'])
            ? CriticalAlertCategory::from($payload['category'])
            : $alert->category;
        $title = array_key_exists('title', $payload) ? trim((string) $payload['title']) : $alert->title;
        $body = array_key_exists('body', $payload) ? trim((string) $payload['body']) : $alert->body;
        $isUrgent = array_key_exists('is_urgent', $payload)
            ? (bool) $payload['is_urgent']
            : $alert->is_urgent;

        $material = $category !== $alert->category
            || $title !== $alert->title
            || $body !== $alert->body;

        $alert->forceFill([
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'is_urgent' => $isUrgent,
            'updated_by_user_id' => $actor->id,
            'version' => $material ? $alert->version + 1 : $alert->version,
        ])->save();

        if ($material) {
            $this->notifyActiveVisitDsps($alert->refresh());
        }

        return $alert->refresh();
    }

    public function deactivate(ClientCriticalAlert $alert, User $actor, ?CarbonInterface $now = null): ClientCriticalAlert
    {
        if (! $alert->is_active) {
            return $alert;
        }

        $now = $now ?? $this->settings->now();

        $alert->forceFill([
            'is_active' => false,
            'deactivated_at' => $now,
            'deactivated_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ])->save();

        return $alert->refresh();
    }

    public function acknowledge(
        Visit $visit,
        ClientCriticalAlert $alert,
        User $actor,
        ?CarbonInterface $now = null,
    ): ClientCriticalAlertAcknowledgment {
        if ($alert->client_id !== $visit->client_id) {
            abort(404);
        }

        if (! $alert->is_active) {
            throw ValidationException::withMessages([
                'alert' => 'This critical alert is no longer active.',
            ]);
        }

        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages([
                'visit' => 'This visit is no longer in progress.',
            ]);
        }

        if ($actor->employee === null || $visit->employee_id !== $actor->employee->id) {
            abort(403);
        }

        $now = $now ?? $this->settings->now();

        /** @var ClientCriticalAlertAcknowledgment $acknowledgment */
        $acknowledgment = ClientCriticalAlertAcknowledgment::query()->firstOrCreate(
            [
                'client_critical_alert_id' => $alert->id,
                'alert_version' => $alert->version,
                'visit_id' => $visit->id,
                'user_id' => $actor->id,
            ],
            [
                'acknowledged_at' => $now,
            ],
        );

        return $acknowledgment;
    }

    /**
     * @return Collection<int, ClientCriticalAlert>
     */
    public function activeForClient(Client $client): Collection
    {
        return ClientCriticalAlert::query()
            ->active()
            ->where('client_id', $client->id)
            ->with(['createdBy', 'updatedBy'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<ClientCriticalAlert>
     */
    public function unacknowledgedForVisit(Visit $visit, User $actor): array
    {
        $active = $this->activeForClient($visit->client)->all();
        $pending = [];

        foreach ($active as $alert) {
            if (! $this->isAcknowledgedOnVisit($alert, $visit, $actor)) {
                $pending[] = $alert;
            }
        }

        return $pending;
    }

    public function isAcknowledgedOnVisit(ClientCriticalAlert $alert, Visit $visit, User $actor): bool
    {
        return ClientCriticalAlertAcknowledgment::query()
            ->where('client_critical_alert_id', $alert->id)
            ->where('alert_version', $alert->version)
            ->where('visit_id', $visit->id)
            ->where('user_id', $actor->id)
            ->exists();
    }

    public function assertAcknowledgedForTaskExecution(User $user, Visit $visit): void
    {
        $pending = $this->unacknowledgedForVisit($visit, $user);

        if ($pending !== []) {
            throw ValidationException::withMessages([
                'critical_alerts' => 'Acknowledge all critical client alerts before continuing with visit tasks.',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForClient(
        Client $client,
        ?Visit $visit = null,
        ?User $viewer = null,
        bool $includeInactive = true,
    ): array {
        $alerts = ClientCriticalAlert::query()
            ->where('client_id', $client->id)
            ->when(! $includeInactive, fn ($query) => $query->active())
            ->with(['createdBy', 'updatedBy', 'deactivatedBy'])
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return $alerts->map(function (ClientCriticalAlert $alert) use ($visit, $viewer): array {
            return $this->serializeAlert($alert, $visit, $viewer);
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForVisit(Visit $visit, ?User $viewer = null): array
    {
        $alerts = $this->activeForClient($visit->client);

        return $alerts->map(function (ClientCriticalAlert $alert) use ($visit, $viewer): array {
            return $this->serializeAlert($alert, $visit, $viewer);
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeAlert(ClientCriticalAlert $alert, ?Visit $visit = null, ?User $viewer = null): array
    {
        $settings = $this->settings;
        $acknowledged = $visit !== null && $viewer !== null
            ? $this->isAcknowledgedOnVisit($alert, $visit, $viewer)
            : false;

        $ack = null;

        if ($acknowledged && $visit !== null && $viewer !== null) {
            $ack = ClientCriticalAlertAcknowledgment::query()
                ->where('client_critical_alert_id', $alert->id)
                ->where('alert_version', $alert->version)
                ->where('visit_id', $visit->id)
                ->where('user_id', $viewer->id)
                ->first();
        }

        return [
            'id' => $alert->id,
            'category' => $alert->category->value,
            'category_label' => $alert->category->label(),
            'title' => $alert->title,
            'body' => $alert->body,
            'version' => $alert->version,
            'is_urgent' => $alert->is_urgent,
            'is_active' => $alert->is_active,
            'created_by_name' => $alert->createdBy?->name,
            'created_at' => $alert->created_at?->toIso8601String(),
            'created_at_label' => $alert->created_at !== null
                ? $settings->formatDateTime($alert->created_at)
                : null,
            'updated_at' => $alert->updated_at?->toIso8601String(),
            'deactivated_at' => $alert->deactivated_at?->toIso8601String(),
            'acknowledged' => $acknowledged,
            'acknowledged_at' => $ack?->acknowledged_at?->toIso8601String(),
            'acknowledged_at_label' => $ack?->acknowledged_at !== null
                ? $settings->formatDateTime($ack->acknowledged_at)
                : null,
            'requires_acknowledgment' => $visit !== null
                && $viewer !== null
                && $alert->is_active
                && ! $acknowledged
                && $visit->status === VisitStatus::InProgress,
        ];
    }

    private function notifyActiveVisitDsps(ClientCriticalAlert $alert): void
    {
        $visits = Visit::query()
            ->where('client_id', $alert->client_id)
            ->where('status', VisitStatus::InProgress)
            ->with(['employee.user', 'client'])
            ->get();

        foreach ($visits as $visit) {
            $user = $visit->employee?->user;

            if ($user === null) {
                continue;
            }

            $urgent = $alert->is_urgent;
            $title = $urgent ? 'Urgent critical client alert' : 'Critical client alert';
            $sourceKey = sprintf(
                'visit.%d.critical-alert.%d.v%d',
                $visit->id,
                $alert->id,
                $alert->version,
            );

            $this->notifications->notify(
                $user,
                InAppNotificationType::Schedule,
                $title,
                sprintf('%s · %s', $visit->client->full_name, $alert->title),
                $sourceKey,
                route('visits.show', $visit),
            );
        }
    }
}
