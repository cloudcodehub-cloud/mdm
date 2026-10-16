<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\ReportType;
use App\Http\Requests\ViewReportRequest;
use App\Models\Incident;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\MdmDocumentFactory;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentController extends Controller
{
    public function __construct(
        private MdmDocumentFactory $documents,
        private DocumentRenderer $renderer,
    ) {}

    public function visitHandoutPreview(Request $request, ScheduledVisit $scheduledVisit): Response
    {
        $user = $this->viewer($request);
        $this->authorize('view', $scheduledVisit);
        $document = $this->documents->visitHandout($scheduledVisit, $user);

        return $this->renderer->preview(
            $document,
            route('scheduled-visits.show', $scheduledVisit),
            route('documents.visit-handout.pdf', $scheduledVisit),
        );
    }

    public function visitHandoutPdf(Request $request, ScheduledVisit $scheduledVisit): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('view', $scheduledVisit);

        return $this->renderer->download($this->documents->visitHandout($scheduledVisit, $user));
    }

    public function completedVisitPreview(Request $request, Visit $visit): Response
    {
        $user = $this->viewer($request);
        $this->authorize('view', $visit);
        $document = $this->documents->completedVisit($visit, $user);

        return $this->renderer->preview(
            $document,
            route('visits.show', $visit),
            route('documents.completed-visit.pdf', $visit),
        );
    }

    public function completedVisitPdf(Request $request, Visit $visit): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('view', $visit);

        return $this->renderer->download($this->documents->completedVisit($visit, $user));
    }

    public function incidentSubmittedPreview(Request $request, Incident $incident): Response
    {
        $user = $this->viewer($request);
        $this->authorize('view', $incident);
        abort_unless($incident->status->isFormal(), 404);
        $document = $this->documents->incidentSubmitted($incident, $user);

        return $this->renderer->preview(
            $document,
            route('incidents.show', $incident),
            route('documents.incident-submitted.pdf', $incident),
        );
    }

    public function incidentSubmittedPdf(Request $request, Incident $incident): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('view', $incident);
        abort_unless($incident->status->isFormal(), 404);

        return $this->renderer->download($this->documents->incidentSubmitted($incident, $user));
    }

    public function incidentFinalPreview(Request $request, Incident $incident): Response
    {
        $user = $this->viewer($request);
        $this->authorize('manage', $incident);
        abort_unless($incident->status === IncidentStatus::Closed, 404);
        $document = $this->documents->incidentFinal($incident, $user);

        return $this->renderer->preview(
            $document,
            route('incidents.show', $incident),
            route('documents.incident-final.pdf', $incident),
        );
    }

    public function incidentFinalPdf(Request $request, Incident $incident): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('manage', $incident);
        abort_unless($incident->status === IncidentStatus::Closed, 404);

        return $this->renderer->download($this->documents->incidentFinal($incident, $user));
    }

    public function hoursAttendancePreview(ViewReportRequest $request, SettingsService $settings): Response
    {
        $user = $this->viewer($request);
        $filters = $request->filters(ReportType::EmployeeAttendance, $settings);
        $document = $this->documents->hoursAttendance($user, $filters);

        return $this->renderer->preview(
            $document,
            route('reports.show', [
                'report' => ReportType::EmployeeAttendance->value,
                ...$this->filterQuery($filters),
            ]),
            route('documents.hours-attendance.pdf', $this->filterQuery($filters)),
        );
    }

    public function hoursAttendancePdf(ViewReportRequest $request, SettingsService $settings): SymfonyResponse
    {
        $user = $this->viewer($request);
        $filters = $request->filters(ReportType::EmployeeAttendance, $settings);

        return $this->renderer->download($this->documents->hoursAttendance($user, $filters));
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return array<string, string>
     */
    private function filterQuery(array $filters): array
    {
        return array_filter($filters, fn (string $value): bool => $value !== '');
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $user;
    }
}
