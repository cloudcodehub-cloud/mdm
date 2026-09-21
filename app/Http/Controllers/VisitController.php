<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\CareOverviewService;
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
    ): Response {
        $this->authorize('view', $visit);

        if ($visit->status === VisitStatus::InProgress) {
            $tasks->generate($visit);
        }

        $visit->load([
            'client.supervisor.user',
            'employee',
            'scheduledVisit.shiftTemplate',
            'tasks.skipReason',
            'exceptions.visitTask',
            'exceptions.reviewedBy',
            'exceptions.resolvedBy',
        ]);

        $user = $request->user();
        $canRecord = $user?->can('recordTask', $visit) ?? false;

        return Inertia::render('visits/show', [
            'visit' => DirectoryPresenter::visitDetail($visit),
            'skip_reasons' => $canRecord ? DirectoryPresenter::skipReasons() : [],
            'care_history' => $user !== null ? $overview->forClient($visit->client, $user)['history'] : [],
            'supervisor_contact' => $this->supervisorContact($visit->client, $user),
            'can' => [
                'clock_in' => $user?->can('clockIn', $visit->scheduledVisit) ?? false,
                'record_tasks' => $canRecord,
                'update_notes' => $user?->can('updateNotes', $visit) ?? false,
                'clock_out' => $user?->can('clockOut', $visit) ?? false,
                'view_exceptions' => $user?->can('viewAny', VisitException::class) ?? false,
                'follow_up' => $visit->exceptions->contains(
                    fn (VisitException $exception): bool => $user?->can('followUp', $exception) ?? false,
                ),
            ],
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
