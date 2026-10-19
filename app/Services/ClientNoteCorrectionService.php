<?php

namespace App\Services;

use App\Enums\ClientAdminHistoryCategory;
use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * MDM 06 Decision 06-18 — Client continuity/operational notes must not be
 * silently overwritten. Authorized corrections keep original and amended text
 * in administrative history with actor and timestamp.
 *
 * Visit Notes remain MDM 05 and are intentionally out of scope here.
 */
class ClientNoteCorrectionService
{
    public function __construct(private ClientAdminHistoryService $history) {}

    /**
     * Validate a notes change and strip the correction-reason field from the
     * mass-assignment payload. Call {@see recordAfterSave} after the Client is saved.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, history: array<string, mixed>|null}
     */
    public function prepareUpdate(Client $client, array $data): array
    {
        if (! array_key_exists('notes', $data)) {
            return ['data' => $data, 'history' => null];
        }

        $incoming = $this->normalize($data['notes'] ?? null);
        $previous = $this->normalize($client->notes);
        $reason = $this->normalize($data['notes_correction_reason'] ?? null);
        unset($data['notes_correction_reason']);
        $data['notes'] = $incoming;

        if ($incoming === $previous) {
            return ['data' => $data, 'history' => null];
        }

        if ($previous === null) {
            return [
                'data' => $data,
                'history' => [
                    'category' => ClientAdminHistoryCategory::Note,
                    'summary' => 'Client continuity note recorded',
                    'old_value' => null,
                    'new_value' => $incoming,
                    'reason' => $reason,
                ],
            ];
        }

        if ($reason === null) {
            throw ValidationException::withMessages([
                'notes_correction_reason' => 'Provide a reason when correcting an existing client note. Original and amended content are retained in history.',
            ]);
        }

        return [
            'data' => $data,
            'history' => [
                'category' => ClientAdminHistoryCategory::Note,
                'summary' => 'Client continuity note corrected',
                'old_value' => $previous,
                'new_value' => $incoming,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $history
     */
    public function recordAfterSave(Client $client, User $actor, array $history): void
    {
        $this->history->record($client, $actor, [
            ...$history,
            'subject' => $client,
        ]);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
