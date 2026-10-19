<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Visit;

/**
 * MDM 06 Decision 06-29 — Client/service/authorization changes must not silently
 * terminate an Active Visit; authorized staff must be warned when one is affected.
 */
class ClientActiveVisitGuard
{
    public function inProgressVisit(Client $client): ?Visit
    {
        return Visit::query()
            ->where('client_id', $client->id)
            ->where('status', VisitStatus::InProgress)
            ->with('employee')
            ->first();
    }

    /**
     * @return array{id: int, dsp_name: string|null, warning: string}|null
     */
    public function warningPayload(Client $client): ?array
    {
        $visit = $this->inProgressVisit($client);

        if ($visit === null) {
            return null;
        }

        return [
            'id' => $visit->id,
            'dsp_name' => $visit->employee?->full_name,
            'warning' => 'An active visit is in progress. This change does not automatically end or invalidate that visit. MDM 05 remains responsible for visit completion.',
        ];
    }

    public function warningMessage(Client $client): ?string
    {
        return $this->warningPayload($client)['warning'] ?? null;
    }
}
