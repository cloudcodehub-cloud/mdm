<?php

namespace App\Services;

use App\Enums\ClientAdminHistoryCategory;
use App\Models\Client;
use App\Models\ClientServiceLocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientServiceLocationService
{
    public function __construct(private ClientAdminHistoryService $history) {}

    /**
     * @param  array{
     *     label: string,
     *     address_line_1: string,
     *     address_line_2?: string|null,
     *     city?: string|null,
     *     state?: string|null,
     *     postal_code?: string|null,
     *     latitude?: float|string|null,
     *     longitude?: float|string|null,
     *     is_primary?: bool,
     *     is_active?: bool
     * }  $payload
     */
    public function create(Client $client, User $actor, array $payload): ClientServiceLocation
    {
        return DB::transaction(function () use ($client, $actor, $payload): ClientServiceLocation {
            $isPrimary = (bool) ($payload['is_primary'] ?? ! $client->serviceLocations()->active()->exists());

            if ($isPrimary) {
                $this->clearPrimary($client);
            }

            $location = ClientServiceLocation::query()->create([
                'client_id' => $client->id,
                'label' => trim($payload['label']),
                'address_line_1' => trim($payload['address_line_1']),
                'address_line_2' => $this->nullableString($payload['address_line_2'] ?? null),
                'city' => $this->nullableString($payload['city'] ?? null),
                'state' => $this->nullableString($payload['state'] ?? null),
                'postal_code' => $this->nullableString($payload['postal_code'] ?? null),
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'is_primary' => $isPrimary,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]);

            if ($isPrimary) {
                $this->mirrorPrimaryToClientAddress($client, $location);
            }

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::ServiceLocation,
                'summary' => 'Service location added',
                'new_value' => $location->displayLabel(),
                'subject' => $location,
            ]);

            return $location->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(ClientServiceLocation $location, User $actor, array $payload): ClientServiceLocation
    {
        return DB::transaction(function () use ($location, $actor, $payload): ClientServiceLocation {
            $client = $location->client;
            $old = $location->displayLabel();
            $isPrimary = array_key_exists('is_primary', $payload)
                ? (bool) $payload['is_primary']
                : $location->is_primary;

            if ($isPrimary) {
                $this->clearPrimary($client, $location->id);
            }

            $location->forceFill([
                'label' => array_key_exists('label', $payload)
                    ? trim((string) $payload['label'])
                    : $location->label,
                'address_line_1' => array_key_exists('address_line_1', $payload)
                    ? trim((string) $payload['address_line_1'])
                    : $location->address_line_1,
                'address_line_2' => array_key_exists('address_line_2', $payload)
                    ? $this->nullableString($payload['address_line_2'])
                    : $location->address_line_2,
                'city' => array_key_exists('city', $payload)
                    ? $this->nullableString($payload['city'])
                    : $location->city,
                'state' => array_key_exists('state', $payload)
                    ? $this->nullableString($payload['state'])
                    : $location->state,
                'postal_code' => array_key_exists('postal_code', $payload)
                    ? $this->nullableString($payload['postal_code'])
                    : $location->postal_code,
                'latitude' => array_key_exists('latitude', $payload)
                    ? $payload['latitude']
                    : $location->latitude,
                'longitude' => array_key_exists('longitude', $payload)
                    ? $payload['longitude']
                    : $location->longitude,
                'is_primary' => $isPrimary,
                'is_active' => array_key_exists('is_active', $payload)
                    ? (bool) $payload['is_active']
                    : $location->is_active,
            ])->save();

            if ($isPrimary) {
                $this->mirrorPrimaryToClientAddress($client, $location);
            }

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::ServiceLocation,
                'summary' => 'Service location updated',
                'old_value' => $old,
                'new_value' => $location->displayLabel(),
                'subject' => $location,
            ]);

            return $location->refresh();
        });
    }

    public function deactivate(ClientServiceLocation $location, User $actor): ClientServiceLocation
    {
        if ($location->is_primary) {
            throw ValidationException::withMessages([
                'location' => 'Set another primary service location before deactivating this one.',
            ]);
        }

        $location->forceFill(['is_active' => false])->save();

        $this->history->record($location->client, $actor, [
            'category' => ClientAdminHistoryCategory::ServiceLocation,
            'summary' => 'Service location deactivated',
            'old_value' => $location->displayLabel(),
            'subject' => $location,
        ]);

        return $location->refresh();
    }

    public function primaryFor(Client $client): ?ClientServiceLocation
    {
        return ClientServiceLocation::query()
            ->where('client_id', $client->id)
            ->active()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function resolveForScheduling(Client $client, ?int $locationId): ClientServiceLocation
    {
        if ($locationId !== null) {
            $location = ClientServiceLocation::query()
                ->where('client_id', $client->id)
                ->active()
                ->whereKey($locationId)
                ->first();

            if ($location === null) {
                throw ValidationException::withMessages([
                    'client_service_location_id' => 'Select an active service location for this client.',
                ]);
            }

            return $location;
        }

        $primary = $this->primaryFor($client);

        if ($primary === null) {
            throw ValidationException::withMessages([
                'client_service_location_id' => 'This client has no active service location.',
            ]);
        }

        return $primary;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotAttributes(ClientServiceLocation $location): array
    {
        return [
            'client_service_location_id' => $location->id,
            'service_location_label' => $location->label,
            'service_address_line_1' => $location->address_line_1,
            'service_address_line_2' => $location->address_line_2,
            'service_city' => $location->city,
            'service_state' => $location->state,
            'service_postal_code' => $location->postal_code,
            'service_latitude' => $location->latitude,
            'service_longitude' => $location->longitude,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForClient(Client $client, bool $includeInactive = false): array
    {
        /** @var Collection<int, ClientServiceLocation> $locations */
        $locations = ClientServiceLocation::query()
            ->where('client_id', $client->id)
            ->when(! $includeInactive, fn ($query) => $query->active())
            ->orderByDesc('is_primary')
            ->orderBy('label')
            ->get();

        return $locations->map(fn (ClientServiceLocation $location): array => [
            'id' => $location->id,
            'label' => $location->label,
            'display_label' => $location->displayLabel(),
            'address_line_1' => $location->address_line_1,
            'address_line_2' => $location->address_line_2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
            'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
            'is_primary' => $location->is_primary,
            'is_active' => $location->is_active,
        ])->values()->all();
    }

    /**
     * @return list<array{id: int, name: string, is_primary: bool}>
     */
    public function optionsForClient(Client $client): array
    {
        return collect($this->serializeForClient($client))
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['display_label'],
                'is_primary' => $row['is_primary'],
            ])
            ->values()
            ->all();
    }

    private function clearPrimary(Client $client, ?int $exceptId = null): void
    {
        ClientServiceLocation::query()
            ->where('client_id', $client->id)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_primary' => false]);
    }

    private function mirrorPrimaryToClientAddress(Client $client, ClientServiceLocation $location): void
    {
        $client->forceFill([
            'address_line_1' => $location->address_line_1,
            'address_line_2' => $location->address_line_2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
        ])->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
