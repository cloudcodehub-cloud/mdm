<?php

namespace App\Services;

use App\Enums\InAppNotificationType;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Models\VisitOutcomeReclassification;
use Illuminate\Support\Collection;

/**
 * MDM 05-18 / 05-27 / 05-10 — Admin visibility for material reconstruction and reclassification.
 * Reuses in-app notifications (source_key dedupe). No external channels.
 */
class MaterialCorrectionAttentionService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private SettingsService $settings,
    ) {}

    public function notifyMaterialCorrection(AttendanceCorrection $correction): void
    {
        if (! $this->isMaterial($correction)) {
            return;
        }

        $correction->loadMissing(['scheduledVisit.client', 'scheduledVisit.employee', 'reviewedBy', 'requestedBy']);
        $scheduled = $correction->scheduledVisit;
        $client = $scheduled?->client?->full_name ?? 'Client';
        $employee = $scheduled?->employee?->full_name ?? 'DSP';
        $kind = $correction->is_evv_reconstruction
            ? 'Material EVV reconstruction'
            : 'Material attendance correction';
        $body = $client.' · '.$employee.' · '
            .($scheduled?->service_date?->toDateString() ?? 'n/a')
            .($correction->is_evv_reconstruction ? ' (reconstructed EVV)' : '');

        $this->notifyAdmins(
            $kind,
            $body,
            'attendance.correction.'.$correction->id.'.material',
            route('attendance.show', $scheduled),
        );
    }

    public function notifyMissedToCancelled(VisitOutcomeReclassification $reclassification): void
    {
        $reclassification->loadMissing(['scheduledVisit.client', 'scheduledVisit.employee']);
        $scheduled = $reclassification->scheduledVisit;
        $client = $scheduled?->client?->full_name ?? 'Client';
        $employee = $scheduled?->employee?->full_name ?? 'DSP';

        $this->notifyAdmins(
            'Missed → Cancelled reclassification',
            $client.' · '.$employee.' · '.($scheduled?->service_date?->toDateString() ?? 'n/a'),
            'attendance.reclassification.'.$reclassification->id.'.missed-cancelled',
            $scheduled !== null ? route('attendance.show', $scheduled) : route('attendance.index'),
        );
    }

    public function isMaterial(AttendanceCorrection $correction): bool
    {
        if (! $correction->isApproved()) {
            return false;
        }

        if ($correction->is_evv_reconstruction) {
            return true;
        }

        $fields = $correction->proposed_fields ?? [];

        if (in_array('times', $fields, true) || in_array('evv_reconstruction', $fields, true)) {
            return true;
        }

        return $correction->original_attendance_status === 'missed'
            || $correction->requestsTimeChange();
    }

    /**
     * Admin Operations attention rows (recent material events).
     *
     * @return list<array<string, mixed>>
     */
    public function operationsRows(?User $viewer, int $limit = 12): array
    {
        if ($viewer === null || ! $viewer->isAdmin()) {
            return [];
        }

        $corrections = AttendanceCorrection::query()
            ->with(['scheduledVisit.client', 'scheduledVisit.employee', 'reviewedBy', 'requestedBy'])
            ->where('status', 'approved')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(fn (AttendanceCorrection $correction): bool => $this->isMaterial($correction))
            ->take($limit);

        $reclassifications = VisitOutcomeReclassification::query()
            ->with(['scheduledVisit.client', 'scheduledVisit.employee', 'reclassifiedBy'])
            ->orderByDesc('reclassified_at')
            ->limit($limit)
            ->get();

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();

        foreach ($corrections as $correction) {
            if (! $this->isMaterial($correction)) {
                continue;
            }

            $scheduled = $correction->scheduledVisit;
            $rows->push([
                'key' => 'correction-'.$correction->id,
                'kind' => $correction->is_evv_reconstruction ? 'evv_reconstruction' : 'material_correction',
                'kind_label' => $correction->is_evv_reconstruction
                    ? 'Material EVV reconstruction'
                    : 'Material attendance correction',
                'service_date' => $scheduled?->service_date?->toDateString(),
                'client_name' => $scheduled?->client?->full_name,
                'employee_name' => $scheduled?->employee?->full_name,
                'actor_name' => $correction->reviewedBy?->name ?? $correction->requestedBy?->name,
                'occurred_at' => $correction->applied_at !== null
                    ? $this->settings->formatDateTime($correction->applied_at)
                    : null,
                'url' => $scheduled !== null ? route('attendance.show', $scheduled) : null,
            ]);
        }

        foreach ($reclassifications as $reclassification) {
            $scheduled = $reclassification->scheduledVisit;
            $rows->push([
                'key' => 'reclassification-'.$reclassification->id,
                'kind' => 'missed_to_cancelled',
                'kind_label' => 'Missed → Cancelled',
                'service_date' => $scheduled?->service_date?->toDateString(),
                'client_name' => $scheduled?->client?->full_name,
                'employee_name' => $scheduled?->employee?->full_name,
                'actor_name' => $reclassification->reclassifiedBy?->name,
                'occurred_at' => $this->settings->formatDateTime($reclassification->reclassified_at),
                'url' => $scheduled !== null ? route('attendance.show', $scheduled) : null,
            ]);
        }

        return $rows
            ->sortByDesc(fn (array $row): string => (string) ($row['occurred_at'] ?? ''))
            ->take($limit)
            ->values()
            ->all();
    }

    private function notifyAdmins(string $title, string $body, string $key, string $url): void
    {
        $admins = User::query()->where('role', Role::Admin)->get();

        foreach ($admins as $admin) {
            $this->notifications->notify(
                $admin,
                InAppNotificationType::MaterialCorrection,
                $title,
                $body,
                $key.'.admin.'.$admin->id,
                $url,
            );
        }
    }
}
