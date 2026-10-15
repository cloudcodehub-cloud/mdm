<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitSafetyInstruction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitSafetyInstructionService
{
    public function __construct(
        private SettingsService $settings,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * @param  array{content: string, is_urgent?: bool}  $payload
     */
    public function issue(Visit $visit, User $actor, array $payload, ?CarbonInterface $now = null): VisitSafetyInstruction
    {
        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages([
                'visit' => 'Urgent safety instructions can only be issued for an active visit.',
            ]);
        }

        $now = $now ?? $this->settings->now();
        $content = trim($payload['content']);

        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => 'Enter the urgent safety instruction.',
            ]);
        }

        $instruction = VisitSafetyInstruction::query()->create([
            'visit_id' => $visit->id,
            'content' => $content,
            'is_urgent' => (bool) ($payload['is_urgent'] ?? true),
            'created_by_user_id' => $actor->id,
            'effective_at' => $now,
        ]);

        $this->notifyDsp($visit, $instruction);

        return $instruction->refresh();
    }

    public function acknowledge(
        Visit $visit,
        VisitSafetyInstruction $instruction,
        User $actor,
        ?CarbonInterface $now = null,
    ): VisitSafetyInstruction {
        if ($instruction->visit_id !== $visit->id) {
            abort(404);
        }

        if ($visit->status !== VisitStatus::InProgress) {
            throw ValidationException::withMessages([
                'visit' => 'This visit is no longer in progress.',
            ]);
        }

        if ($actor->employee === null || $visit->employee_id !== $actor->employee->id) {
            abort(403);
        }

        if ($instruction->isAcknowledged()) {
            return $instruction;
        }

        $now = $now ?? $this->settings->now();

        return DB::transaction(function () use ($instruction, $actor, $now): VisitSafetyInstruction {
            /** @var VisitSafetyInstruction $locked */
            $locked = VisitSafetyInstruction::query()
                ->whereKey($instruction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->acknowledged_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'acknowledged_at' => $now,
                'acknowledged_by_user_id' => $actor->id,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @return list<VisitSafetyInstruction>
     */
    public function unacknowledgedForVisit(Visit $visit): array
    {
        return VisitSafetyInstruction::query()
            ->where('visit_id', $visit->id)
            ->whereNull('acknowledged_at')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function assertAcknowledgedForTaskExecution(Visit $visit): void
    {
        if ($this->unacknowledgedForVisit($visit) !== []) {
            throw ValidationException::withMessages([
                'visit_safety_instructions' => 'Acknowledge all urgent visit safety instructions before continuing with visit tasks.',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForVisit(Visit $visit): array
    {
        $settings = $this->settings;

        return VisitSafetyInstruction::query()
            ->where('visit_id', $visit->id)
            ->with(['createdBy', 'acknowledgedBy'])
            ->orderBy('id')
            ->get()
            ->map(function (VisitSafetyInstruction $instruction) use ($settings): array {
                return [
                    'id' => $instruction->id,
                    'content' => $instruction->content,
                    'is_urgent' => $instruction->is_urgent,
                    'created_by_name' => $instruction->createdBy?->name,
                    'effective_at' => $instruction->effective_at->toIso8601String(),
                    'effective_at_label' => $settings->formatDateTime($instruction->effective_at),
                    'acknowledged' => $instruction->isAcknowledged(),
                    'acknowledged_at' => $instruction->acknowledged_at?->toIso8601String(),
                    'acknowledged_at_label' => $instruction->acknowledged_at !== null
                        ? $settings->formatDateTime($instruction->acknowledged_at)
                        : null,
                    'acknowledged_by_name' => $instruction->acknowledgedBy?->name,
                    'requires_acknowledgment' => ! $instruction->isAcknowledged(),
                ];
            })
            ->values()
            ->all();
    }

    private function notifyDsp(Visit $visit, VisitSafetyInstruction $instruction): void
    {
        $visit->loadMissing(['employee.user', 'client']);
        $user = $visit->employee?->user;

        if ($user === null) {
            return;
        }

        $title = $instruction->is_urgent
            ? 'Urgent visit safety instruction'
            : 'Visit safety instruction';
        $sourceKey = sprintf('visit.%d.safety-instruction.%d', $visit->id, $instruction->id);

        $this->notifications->notify(
            $user,
            InAppNotificationType::Schedule,
            $title,
            sprintf('%s · %s', $visit->client->full_name, $instruction->content),
            $sourceKey,
            route('visits.show', $visit),
        );
    }
}
