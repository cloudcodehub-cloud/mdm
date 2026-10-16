<?php

namespace App\Services;

use App\Enums\ClientAdminHistoryCategory;
use App\Enums\ClientContactRole;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientContactService
{
    public function __construct(private ClientAdminHistoryService $history) {}

    /**
     * @param  array{
     *     role: string,
     *     name: string,
     *     relationship?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     notes?: string|null,
     *     is_primary?: bool
     * }  $payload
     */
    public function create(Client $client, User $actor, array $payload): ClientContact
    {
        return DB::transaction(function () use ($client, $actor, $payload): ClientContact {
            $role = ClientContactRole::from($payload['role']);
            $isPrimary = (bool) ($payload['is_primary'] ?? $role === ClientContactRole::Emergency);

            if ($isPrimary) {
                $this->clearPrimary($client, $role);
            }

            $contact = ClientContact::query()->create([
                'client_id' => $client->id,
                'role' => $role,
                'name' => trim($payload['name']),
                'relationship' => $this->nullableString($payload['relationship'] ?? null),
                'phone' => $this->nullableString($payload['phone'] ?? null),
                'email' => $this->nullableString($payload['email'] ?? null),
                'notes' => $this->nullableString($payload['notes'] ?? null),
                'is_primary' => $isPrimary,
            ]);

            $this->syncLegacyEmergencyFields($client);

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Contact,
                'summary' => 'Contact added ('.$role->label().')',
                'new_value' => $contact->name,
                'subject' => $contact,
            ]);

            return $contact->refresh();
        });
    }

    /**
     * @param  array{
     *     role?: string,
     *     name?: string,
     *     relationship?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     notes?: string|null,
     *     is_primary?: bool
     * }  $payload
     */
    public function update(ClientContact $contact, User $actor, array $payload): ClientContact
    {
        return DB::transaction(function () use ($contact, $actor, $payload): ClientContact {
            $client = $contact->client;
            $role = isset($payload['role'])
                ? ClientContactRole::from($payload['role'])
                : $contact->role;
            $isPrimary = array_key_exists('is_primary', $payload)
                ? (bool) $payload['is_primary']
                : $contact->is_primary;

            if ($isPrimary) {
                $this->clearPrimary($client, $role, $contact->id);
            }

            $oldName = $contact->name;

            $contact->forceFill([
                'role' => $role,
                'name' => array_key_exists('name', $payload) ? trim((string) $payload['name']) : $contact->name,
                'relationship' => array_key_exists('relationship', $payload)
                    ? $this->nullableString($payload['relationship'])
                    : $contact->relationship,
                'phone' => array_key_exists('phone', $payload)
                    ? $this->nullableString($payload['phone'])
                    : $contact->phone,
                'email' => array_key_exists('email', $payload)
                    ? $this->nullableString($payload['email'])
                    : $contact->email,
                'notes' => array_key_exists('notes', $payload)
                    ? $this->nullableString($payload['notes'])
                    : $contact->notes,
                'is_primary' => $isPrimary,
            ])->save();

            $this->syncLegacyEmergencyFields($client);

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Contact,
                'summary' => 'Contact updated ('.$role->label().')',
                'old_value' => $oldName,
                'new_value' => $contact->name,
                'subject' => $contact,
            ]);

            return $contact->refresh();
        });
    }

    public function delete(ClientContact $contact, User $actor): void
    {
        DB::transaction(function () use ($contact, $actor): void {
            $client = $contact->client;
            $role = $contact->role;
            $name = $contact->name;

            $contact->delete();
            $this->syncLegacyEmergencyFields($client);

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Contact,
                'summary' => 'Contact removed ('.$role->label().')',
                'old_value' => $name,
            ]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForViewer(Client $client, User $viewer, bool $fullAccess = true): array
    {
        /** @var Collection<int, ClientContact> $contacts */
        $contacts = $client->relationLoaded('contacts')
            ? $client->contacts
            : $client->contacts()->orderByDesc('is_primary')->orderBy('id')->get();

        return $contacts
            ->filter(function (ClientContact $contact) use ($viewer, $fullAccess): bool {
                if ($viewer->isAdmin() || $viewer->isSupervisor()) {
                    return true;
                }

                if (! $viewer->isDsp()) {
                    return false;
                }

                return $fullAccess && $contact->role->visibleToDsp();
            })
            ->map(fn (ClientContact $contact): array => $this->serialize($contact))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ClientContact $contact): array
    {
        return [
            'id' => $contact->id,
            'role' => $contact->role->value,
            'role_label' => $contact->role->label(),
            'name' => $contact->name,
            'relationship' => $contact->relationship,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'notes' => $contact->notes,
            'is_primary' => $contact->is_primary,
            'visible_to_dsp' => $contact->role->visibleToDsp(),
        ];
    }

    public function syncLegacyEmergencyFields(Client $client): void
    {
        $primary = ClientContact::query()
            ->where('client_id', $client->id)
            ->where('role', ClientContactRole::Emergency)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();

        $client->forceFill([
            'emergency_contact_name' => $primary?->name,
            'emergency_contact_relationship' => $primary?->relationship,
            'emergency_contact_phone' => $primary?->phone,
        ])->save();
    }

    private function clearPrimary(Client $client, ClientContactRole $role, ?int $exceptId = null): void
    {
        ClientContact::query()
            ->where('client_id', $client->id)
            ->where('role', $role)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_primary' => false]);
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
