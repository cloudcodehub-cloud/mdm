<?php

namespace App\Services;

use App\Models\User;
use App\Models\Visit;
use App\Models\VisitNoteHistoryEvent;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * MDM 01 §2.30 / MDM 05 — In-visit Visit Note (and wrap-up note) changes keep
 * previous/new content, actor, and timestamp. Corrections of existing Visit Notes
 * require a reason. Completed visits stay read-only except via Request Correction.
 */
class VisitNoteHistoryService
{
    public const FIELD_VISIT_NOTES = 'visit_notes';

    public const FIELD_HANDOVER_NOTE = 'handover_note';

    /**
     * Apply note fields, recording history for each changed field.
     *
     * @param  array<string, mixed>  $payload
     * @return array{visit_notes: string|null, handover_note: string|null}
     */
    public function apply(Visit $visit, User $actor, array $payload): array
    {
        $visitNotes = array_key_exists('visit_notes', $payload)
            ? $this->normalize($payload['visit_notes'] ?? null)
            : $this->normalize($visit->visit_notes);
        $handover = array_key_exists('handover_note', $payload)
            ? $this->normalize($payload['handover_note'] ?? null)
            : $this->normalize($visit->handover_note);
        $reason = $this->normalize($payload['notes_correction_reason'] ?? null);

        $this->recordFieldChange(
            $visit,
            $actor,
            self::FIELD_VISIT_NOTES,
            $this->normalize($visit->visit_notes),
            $visitNotes,
            $reason,
            requireReasonWhenAmending: true,
        );

        $this->recordFieldChange(
            $visit,
            $actor,
            self::FIELD_HANDOVER_NOTE,
            $this->normalize($visit->handover_note),
            $handover,
            $reason,
            requireReasonWhenAmending: false,
        );

        return [
            'visit_notes' => $visitNotes,
            'handover_note' => $handover,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForVisit(Visit $visit): array
    {
        /** @var Collection<int, VisitNoteHistoryEvent> $events */
        $events = VisitNoteHistoryEvent::query()
            ->where('visit_id', $visit->id)
            ->with('actor')
            ->orderByDesc('id')
            ->get();

        $settings = app(SettingsService::class);

        return $events->map(function (VisitNoteHistoryEvent $event) use ($settings): array {
            return [
                'id' => $event->id,
                'field_key' => $event->field_key,
                'field_label' => $event->field_key === self::FIELD_HANDOVER_NOTE
                    ? 'Wrap-up / handover note'
                    : 'Visit notes',
                'previous_content' => $event->previous_content,
                'new_content' => $event->new_content,
                'reason' => $event->reason,
                'actor_name' => $event->actor?->name,
                'occurred_at' => $event->created_at?->toIso8601String(),
                'occurred_at_label' => $event->created_at === null
                    ? null
                    : $settings->formatDateTime($event->created_at),
            ];
        })->values()->all();
    }

    private function recordFieldChange(
        Visit $visit,
        User $actor,
        string $fieldKey,
        ?string $previous,
        ?string $next,
        ?string $reason,
        bool $requireReasonWhenAmending,
    ): void {
        if ($previous === $next) {
            return;
        }

        if ($requireReasonWhenAmending && $previous !== null && ($reason === null || $reason === '')) {
            throw ValidationException::withMessages([
                'notes_correction_reason' => 'Provide a reason when correcting or removing an existing Visit Note. Original content is retained in history.',
            ]);
        }

        VisitNoteHistoryEvent::query()->create([
            'visit_id' => $visit->id,
            'field_key' => $fieldKey,
            'previous_content' => $previous,
            'new_content' => $next,
            'actor_user_id' => $actor->id,
            'reason' => $previous !== null ? $reason : null,
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
