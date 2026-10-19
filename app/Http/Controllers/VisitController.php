<?php

namespace App\Http\Controllers;

use App\Enums\GpsFallbackReason;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\ActiveVisitAttentionService;
use App\Services\CareOverviewService;
use App\Services\CriticalAlertService;
use App\Services\IncidentService;
use App\Services\VisitNoteHistoryService;
use App\Services\VisitSafetyCheckService;
use App\Services\VisitSafetyInstructionService;
use App\Services\VisitTaskGenerator;
use App\Support\DirectoryPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function show(
        Request $request,
        Visit $visit,
        VisitTaskGenerator $tasks,
        CareOverviewService $overview,
        VisitSafetyCheckService $safetyChecks,
        ActiveVisitAttentionService $attention,
        CriticalAlertService $criticalAlerts,
        VisitSafetyInstructionService $safetyInstructions,
        IncidentService $incidents,
        VisitNoteHistoryService $noteHistory,
    ): Response {
        $this->authorize('view', $visit);

        if ($visit->status === VisitStatus::InProgress) {
            $tasks->generate($visit);
            $safetyChecks->evaluateVisit($visit);
        }

        $visit->load([
            'client.supervisor.user',
            'employee',
            'scheduledVisit.shiftTemplate',
            'administrativelyClosedBy',
            'tasks.skipReason',
            'safetyChecks',
            'safetyInstructions.createdBy',
            'safetyInstructions.acknowledgedBy',
            'exceptions.visitTask',
            'exceptions.reviewedBy',
            'exceptions.resolvedBy',
        ]);

        $user = $request->user();
        $canRecord = $user?->can('recordTask', $visit) ?? false;
        $minutesPastEnd = $attention->minutesPastScheduledEnd($visit);
        $criticalAlertPayload = $criticalAlerts->serializeForVisit($visit, $user);
        $safetyInstructionPayload = $safetyInstructions->serializeForVisit($visit);
        $pendingCritical = collect($criticalAlertPayload)->contains(
            fn (array $alert): bool => (bool) ($alert['requires_acknowledgment'] ?? false),
        );
        $pendingInstructions = collect($safetyInstructionPayload)->contains(
            fn (array $instruction): bool => (bool) ($instruction['requires_acknowledgment'] ?? false),
        );
        $tasksGated = $pendingCritical || $pendingInstructions;
        $incidentPayload = $user !== null
            ? $incidents->serializeForVisit($visit, $user)
            : [
                'draft' => null,
                'submitted' => [],
                'categories' => [],
                'acknowledgment_threshold_minutes' => $incidents->acknowledgmentThresholdMinutes(),
                'has_unresolved_draft' => false,
            ];

        return Inertia::render('visits/show', [
            'visit' => [
                ...DirectoryPresenter::visitDetail($visit, [
                    'minutes_past_scheduled_end' => $minutesPastEnd,
                    'requires_extended_visit_reason' => $attention->requiresExtendedVisitReason($visit),
                    'past_scheduled_end' => ($minutesPastEnd ?? 0) > 0,
                ]),
                'critical_alerts' => $criticalAlertPayload,
                'safety_instructions' => $safetyInstructionPayload,
                'tasks_gated_by_safety' => $tasksGated,
                'incidents' => $incidentPayload,
                // MDM 01 §2.30 — note history for viewers authorized to see the Visit.
                'note_history' => ($user?->can('view', $visit) ?? false)
                    ? $noteHistory->serializeForVisit($visit)
                    : [],
            ],
            'skip_reasons' => $canRecord && ! $tasksGated ? DirectoryPresenter::skipReasons() : [],
            'care_history' => $user !== null ? $overview->forClient($visit->client, $user)['history'] : [],
            'supervisor_contact' => $this->supervisorContact($visit->client, $user),
            'gps_fallback_reasons' => GpsFallbackReason::options(),
            'can' => [
                'clock_in' => $user?->can('clockIn', $visit->scheduledVisit) ?? false,
                'record_tasks' => $canRecord && ! $tasksGated,
                'record_safety' => $user?->can('recordSafety', $visit) ?? false,
                'update_notes' => $user?->can('updateNotes', $visit) ?? false,
                'clock_out' => $user?->can('clockOut', $visit) ?? false,
                'issue_safety_instruction' => $user?->can('issueSafetyInstruction', $visit) ?? false,
                'administratively_close' => $user?->can('administrativelyClose', $visit) ?? false,
                'report_incident' => $user?->can('create', [\App\Models\Incident::class, $visit]) ?? false,
                'request_correction' => $visit->status === VisitStatus::Completed
                    && ($user?->can('create', [AttendanceCorrection::class, $visit->scheduledVisit]) ?? false),
                'view_exceptions' => $user?->can('viewAny', VisitException::class) ?? false,
                'view_note_history' => $user?->can('view', $visit) ?? false,
                'follow_up' => $visit->exceptions->contains(
                    fn (VisitException $exception): bool => $user?->can('followUp', $exception) ?? false,
                ),
            ],
            'documents' => [
                'completed_visit' => $visit->status === VisitStatus::Completed
                    ? route('documents.completed-visit.preview', $visit)
                    : null,
            ],
            'attendance_url' => $visit->status === VisitStatus::Completed
                ? route('attendance.show', $visit->scheduledVisit)
                : null,
        ]);
    }

    /**
     * @return array{user_id: int, name: string, role_label: string, available: bool}|null
     */
    private function supervisorContact(Client $client, ?User $viewer): ?array
    {
        $supervisor = $client->supervisor;
        $supervisorUser = $supervisor?->user;

        if ($supervisor === null || $supervisorUser === null) {
            return null;
        }

        return [
            'user_id' => $supervisorUser->id,
            'name' => $supervisor->full_name,
            'role_label' => 'Supervisor',
            'available' => $supervisorUser->canMessage() && $supervisorUser->id !== $viewer?->id,
        ];
    }
}
