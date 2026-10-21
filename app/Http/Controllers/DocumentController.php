<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\ReportType;
use App\Enums\RetainedDocumentKey;
use App\Http\Requests\ViewReportRequest;
use App\Models\Incident;
use App\Models\RetainedDocumentArtifact;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\MdmDocumentFactory;
use App\Services\Documents\RetainedDocumentService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentController extends Controller
{
    public function __construct(
        private MdmDocumentFactory $documents,
        private DocumentRenderer $renderer,
        private RetainedDocumentService $retained,
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

        $artifact = $this->retained->currentFor($incident, RetainedDocumentKey::IncidentSubmitted);
        $downloadUrl = route('documents.incident-submitted.pdf', $incident);

        if ($artifact !== null) {
            return $this->renderer->retainedPreview(
                $artifact->title,
                route('incidents.show', $incident),
                $downloadUrl,
                route('documents.retained.inline', $artifact),
            );
        }

        $document = $this->documents->incidentSubmitted($incident, $user);

        return $this->renderer->preview(
            $document,
            route('incidents.show', $incident),
            $downloadUrl,
        );
    }

    public function incidentSubmittedPdf(Request $request, Incident $incident): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('view', $incident);
        abort_unless($incident->status->isFormal(), 404);

        $artifact = $this->retained->currentFor($incident, RetainedDocumentKey::IncidentSubmitted);
        if ($artifact !== null) {
            return $this->retained->download($artifact);
        }

        return $this->renderer->download($this->documents->incidentSubmitted($incident, $user));
    }

    public function incidentFinalPreview(Request $request, Incident $incident): Response
    {
        $user = $this->viewer($request);
        $this->authorize('manage', $incident);
        abort_unless($incident->status === IncidentStatus::Closed, 404);

        $artifact = $this->retained->currentFor($incident, RetainedDocumentKey::IncidentFinal);
        $downloadUrl = route('documents.incident-final.pdf', $incident);

        if ($artifact !== null) {
            return $this->renderer->retainedPreview(
                $artifact->title,
                route('incidents.show', $incident),
                $downloadUrl,
                route('documents.retained.inline', $artifact),
            );
        }

        $document = $this->documents->incidentFinal($incident, $user);

        return $this->renderer->preview(
            $document,
            route('incidents.show', $incident),
            $downloadUrl,
        );
    }

    public function incidentFinalPdf(Request $request, Incident $incident): SymfonyResponse
    {
        $user = $this->viewer($request);
        $this->authorize('manage', $incident);
        abort_unless($incident->status === IncidentStatus::Closed, 404);

        $artifact = $this->retained->currentFor($incident, RetainedDocumentKey::IncidentFinal);
        if ($artifact !== null) {
            return $this->retained->download($artifact);
        }

        return $this->renderer->download($this->documents->incidentFinal($incident, $user));
    }

    public function retainedPreview(Request $request, RetainedDocumentArtifact $artifact): Response
    {
        $this->viewer($request);
        $this->authorize('view', $artifact);

        return $this->renderer->retainedPreview(
            $artifact->title,
            $this->retainedBackUrl($artifact),
            route('documents.retained.download', $artifact),
            route('documents.retained.inline', $artifact),
        );
    }

    public function retainedDownload(Request $request, RetainedDocumentArtifact $artifact): SymfonyResponse
    {
        $this->viewer($request);
        $this->authorize('view', $artifact);

        return $this->retained->download($artifact);
    }

    public function retainedInline(Request $request, RetainedDocumentArtifact $artifact): SymfonyResponse
    {
        $this->viewer($request);
        $this->authorize('view', $artifact);

        return $this->retained->download($artifact, inline: true);
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

    private function retainedBackUrl(RetainedDocumentArtifact $artifact): string
    {
        $source = $artifact->source;

        if ($source instanceof Incident) {
            return route('incidents.show', $source);
        }

        return route('admin.documents.index');
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
