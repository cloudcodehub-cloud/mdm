<?php

namespace App\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\ClientAdminHistoryCategory;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientAuthorizationService
{
    public function __construct(
        private ClientAdminHistoryService $history,
        private ClientActiveVisitGuard $activeVisits,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{authorization: ClientAuthorization, active_visit_warning: string|null}
     */
    public function create(Client $client, User $actor, array $payload): array
    {
        return DB::transaction(function () use ($client, $actor, $payload): array {
            $authorization = ClientAuthorization::query()->create([
                'client_id' => $client->id,
                'authorization_number' => trim((string) $payload['authorization_number']),
                'payer' => trim((string) $payload['payer']),
                'service_type' => trim((string) $payload['service_type']),
                'starts_on' => $payload['starts_on'],
                'ends_on' => $payload['ends_on'] ?? null,
                'authorized_units' => $payload['authorized_units'],
                'unit' => AuthorizationUnit::from((string) $payload['unit']),
                'status' => AuthorizationStatus::from((string) $payload['status']),
                'notes' => $this->nullableString($payload['notes'] ?? null),
            ]);

            $this->history->record($client, $actor, [
                'category' => ClientAdminHistoryCategory::Authorization,
                'summary' => 'Authorization created ('.$authorization->authorization_number.')',
                'new_value' => $this->summaryLine($authorization),
                'subject' => $authorization,
            ]);

            return [
                'authorization' => $authorization,
                'active_visit_warning' => $this->activeVisits->warningMessage($client),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{authorization: ClientAuthorization, active_visit_warning: string|null}
     */
    public function update(ClientAuthorization $authorization, User $actor, array $payload): array
    {
        return DB::transaction(function () use ($authorization, $actor, $payload): array {
            $client = $authorization->client;
            $before = $this->summaryLine($authorization);

            $authorization->forceFill([
                'authorization_number' => trim((string) $payload['authorization_number']),
                'payer' => trim((string) $payload['payer']),
                'service_type' => trim((string) $payload['service_type']),
                'starts_on' => $payload['starts_on'],
                'ends_on' => $payload['ends_on'] ?? null,
                'authorized_units' => $payload['authorized_units'],
                'unit' => AuthorizationUnit::from((string) $payload['unit']),
                'status' => AuthorizationStatus::from((string) $payload['status']),
                'notes' => array_key_exists('notes', $payload)
                    ? $this->nullableString($payload['notes'])
                    : $authorization->notes,
            ])->save();

            $authorization->refresh();
            $after = $this->summaryLine($authorization);

            if ($before !== $after) {
                $this->history->record($client, $actor, [
                    'category' => ClientAdminHistoryCategory::Authorization,
                    'summary' => 'Authorization updated ('.$authorization->authorization_number.')',
                    'old_value' => $before,
                    'new_value' => $after,
                    'subject' => $authorization,
                ]);
            }

            return [
                'authorization' => $authorization,
                'active_visit_warning' => $this->activeVisits->warningMessage($client),
            ];
        });
    }

    private function summaryLine(ClientAuthorization $authorization): string
    {
        $ends = $authorization->ends_on?->toDateString() ?? 'open';

        return implode(' · ', [
            $authorization->authorization_number,
            $authorization->payer,
            $authorization->service_type,
            $authorization->starts_on->toDateString().'–'.$ends,
            $authorization->authorized_units.' '.$authorization->unit->value,
            $authorization->status->value,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
