<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array{requested_clocked_in_at: ?string, requested_clocked_out_at: ?string, reason: string, note: ?string}  $data
     */
    public function submit(ScheduledVisit $scheduledVisit, User $user, array $data): AttendanceCorrection
    {
        $scheduledVisit->loadMissing(['visit', 'attendanceCorrections']);
        $visit = $scheduledVisit->visit;

        if ($visit === null) {
            throw ValidationException::withMessages([
                'visit' => 'A clock record is required before requesting an attendance correction.',
            ]);
        }

        if ($scheduledVisit->attendanceCorrections->contains(
            fn (AttendanceCorrection $correction): bool => $correction->isPending(),
        )) {
            throw ValidationException::withMessages([
                'status' => 'A correction request is already pending for this attendance record.',
            ]);
        }

        $requestedIn = $this->settings->parseLocalDateTime($data['requested_clocked_in_at'] ?? null);
        $requestedOut = $this->settings->parseLocalDateTime($data['requested_clocked_out_at'] ?? null);

        if ($requestedIn === null && $requestedOut === null) {
            throw ValidationException::withMessages([
                'requested_clocked_in_at' => 'Provide a corrected start time, end time, or both.',
            ]);
        }

        $effectiveStart = $requestedIn ?? $visit->clocked_in_at;
        $effectiveEnd = $requestedOut ?? $visit->clocked_out_at;

        if ($effectiveEnd !== null && $effectiveEnd->lte($effectiveStart)) {
            throw ValidationException::withMessages([
                'requested_clocked_out_at' => 'Corrected end time must be after the start time.',
            ]);
        }

        $attributes = [
            'scheduled_visit_id' => $scheduledVisit->id,
            'visit_id' => $visit->id,
            'original_clocked_in_at' => $visit->clocked_in_at,
            'original_clocked_out_at' => $visit->clocked_out_at,
            'requested_clocked_in_at' => $requestedIn,
            'requested_clocked_out_at' => $requestedOut,
            'reason' => trim($data['reason']),
            'note' => $this->normalizeNote($data['note'] ?? null),
            'requested_by_user_id' => $user->id,
        ];

        if ($user->isAdmin()) {
            $attributes['status'] = AttendanceCorrectionStatus::Approved;
            $attributes['reviewed_by_user_id'] = $user->id;
            $attributes['reviewed_at'] = now();
            $attributes['review_note'] = $this->normalizeNote($data['note'] ?? null);
        } else {
            $attributes['status'] = AttendanceCorrectionStatus::Pending;
        }

        return AttendanceCorrection::query()->create($attributes);
    }

    public function approve(AttendanceCorrection $correction, User $user, ?string $reviewNote = null): AttendanceCorrection
    {
        $this->assertPending($correction);

        $correction->forceFill([
            'status' => AttendanceCorrectionStatus::Approved,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $this->normalizeNote($reviewNote),
        ])->save();

        return $correction->refresh();
    }

    public function reject(AttendanceCorrection $correction, User $user, ?string $reviewNote = null): AttendanceCorrection
    {
        $this->assertPending($correction);

        $correction->forceFill([
            'status' => AttendanceCorrectionStatus::Rejected,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $this->normalizeNote($reviewNote),
        ])->save();

        return $correction->refresh();
    }

    private function assertPending(AttendanceCorrection $correction): void
    {
        if (! $correction->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending correction requests can be reviewed.',
            ]);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }
}
