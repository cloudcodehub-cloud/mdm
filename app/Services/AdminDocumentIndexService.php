<?php

namespace App\Services;

use App\Enums\AdminDocumentCategory;
use App\Enums\AdminDocumentOrigin;
use App\Enums\IncidentStatus;
use App\Enums\RetainedDocumentKey;
use App\Enums\VisitStatus;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\Incident;
use App\Models\RetainedDocumentArtifact;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * MDM 10 — Admin Documents discovery index over source-owned records.
 * Not a second storage system or generic upload bucket.
 */
class AdminDocumentIndexService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     category?: string|null,
     *     origin?: string|null,
     *     source_area?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $filters
     * @return array{
     *     documents: LengthAwarePaginator,
     *     filters: array<string, string|null>,
     *     filter_options: array<string, list<array{value: string, label: string}>>
     * }
     */
    public function index(User $actor, array $filters = [], int $perPage = 25): array
    {
        abort_unless($actor->isAdmin(), 403);

        $normalized = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
            'origin' => trim((string) ($filters['origin'] ?? '')),
            'source_area' => trim((string) ($filters['source_area'] ?? '')),
            'from' => trim((string) ($filters['from'] ?? '')),
            'to' => trim((string) ($filters['to'] ?? '')),
        ];

        $entries = $this->collect()
            ->filter(fn (array $row): bool => $this->matches($row, $normalized))
            ->sortByDesc(fn (array $row): string => (string) $row['dated_at'])
            ->values();

        $page = max(1, (int) request()->integer('page', 1));
        $total = $entries->count();
        $slice = $entries->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => route('admin.documents.index'),
                'query' => array_filter($normalized, fn (?string $value): bool => filled($value)),
            ],
        );

        return [
            'documents' => $paginator,
            'filters' => $normalized,
            'filter_options' => $this->filterOptions(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collect(): Collection
    {
        $rows = collect();

        EmployeeCredential::query()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->whereNotNull('document_path')
            ->where('document_path', '!=', '')
            ->orderByDesc('id')
            ->get()
            ->each(function (EmployeeCredential $credential) use ($rows): void {
                $category = AdminDocumentCategory::EmployeeCredential;
                $rows->push($this->entry(
                    key: 'credential:'.$credential->id,
                    category: $category,
                    title: $credential->name,
                    subtitle: $credential->type->label(),
                    datedAt: $credential->updated_at ?? $credential->created_at,
                    employeeName: $credential->employee?->full_name,
                    employeeId: $credential->employee_id,
                    status: $credential->status->value,
                    sourceUrl: route('employees.show', $credential->employee_id),
                    previewUrl: null,
                    downloadUrl: route('admin.documents.credentials.download', $credential),
                ));
            });

        EmployeeTraining::query()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->whereNotNull('document_path')
            ->where('document_path', '!=', '')
            ->orderByDesc('id')
            ->get()
            ->each(function (EmployeeTraining $training) use ($rows): void {
                $category = AdminDocumentCategory::EmployeeTraining;
                $rows->push($this->entry(
                    key: 'training:'.$training->id,
                    category: $category,
                    title: $training->title,
                    subtitle: $training->provider,
                    datedAt: $training->updated_at ?? $training->created_at,
                    employeeName: $training->employee?->full_name,
                    employeeId: $training->employee_id,
                    status: $training->status->value,
                    sourceUrl: route('employees.show', $training->employee_id),
                    previewUrl: null,
                    downloadUrl: route('admin.documents.trainings.download', $training),
                ));
            });

        $retainedIncidentIds = $this->appendRetainedIncidentArtifacts($rows);

        Incident::query()
            ->with(['client:id,first_name,last_name', 'reporter:id,first_name,last_name'])
            ->whereIn('status', [
                IncidentStatus::Submitted,
                IncidentStatus::UnderReview,
                IncidentStatus::Closed,
            ])
            ->orderByDesc('id')
            ->get()
            ->each(function (Incident $incident) use ($rows, $retainedIncidentIds): void {
                // Prefer retained exact binaries (MDM 10-15). Virtual on-demand rows
                // remain only for legacy formal incidents without a retained snapshot.
                if (! isset($retainedIncidentIds[$incident->id][RetainedDocumentKey::IncidentSubmitted->value])) {
                    $category = AdminDocumentCategory::IncidentSubmittedPdf;
                    $rows->push($this->entry(
                        key: 'incident-submitted:'.$incident->id,
                        category: $category,
                        title: 'Incident submitted report #'.$incident->id,
                        subtitle: $incident->client?->full_name,
                        datedAt: $incident->submitted_at ?? $incident->updated_at ?? $incident->created_at,
                        employeeName: $incident->reporter?->full_name,
                        employeeId: $incident->reporter_employee_id,
                        clientName: $incident->client?->full_name,
                        clientId: $incident->client_id,
                        status: $incident->status->label(),
                        sourceUrl: route('incidents.show', $incident),
                        previewUrl: route('documents.incident-submitted.preview', $incident),
                        downloadUrl: route('documents.incident-submitted.pdf', $incident),
                    ));
                }

                if (
                    $incident->status === IncidentStatus::Closed
                    && ! isset($retainedIncidentIds[$incident->id][RetainedDocumentKey::IncidentFinal->value])
                ) {
                    $final = AdminDocumentCategory::IncidentFinalPdf;
                    $rows->push($this->entry(
                        key: 'incident-final:'.$incident->id,
                        category: $final,
                        title: 'Incident final report #'.$incident->id,
                        subtitle: $incident->client?->full_name,
                        datedAt: $incident->closed_at ?? $incident->updated_at ?? $incident->created_at,
                        employeeName: $incident->reporter?->full_name,
                        employeeId: $incident->reporter_employee_id,
                        clientName: $incident->client?->full_name,
                        clientId: $incident->client_id,
                        status: $incident->status->label(),
                        sourceUrl: route('incidents.show', $incident),
                        previewUrl: route('documents.incident-final.preview', $incident),
                        downloadUrl: route('documents.incident-final.pdf', $incident),
                    ));
                }
            });

        Visit::query()
            ->with([
                'client:id,first_name,last_name',
                'employee:id,first_name,last_name',
                'scheduledVisit:id',
            ])
            ->where('status', VisitStatus::Completed)
            ->orderByDesc('id')
            ->get()
            ->each(function (Visit $visit) use ($rows): void {
                $category = AdminDocumentCategory::CompletedVisitPdf;
                $rows->push($this->entry(
                    key: 'completed-visit:'.$visit->id,
                    category: $category,
                    title: 'Completed Visit report #'.$visit->id,
                    subtitle: $visit->client?->full_name,
                    datedAt: $visit->clocked_out_at ?? $visit->updated_at ?? $visit->created_at,
                    employeeName: $visit->employee?->full_name,
                    employeeId: $visit->employee_id,
                    clientName: $visit->client?->full_name,
                    clientId: $visit->client_id,
                    status: $visit->status->value,
                    sourceUrl: route('visits.show', $visit),
                    previewUrl: route('documents.completed-visit.preview', $visit),
                    downloadUrl: route('documents.completed-visit.pdf', $visit),
                ));
            });

        $hours = AdminDocumentCategory::HoursAttendanceReport;
        $rows->push($this->entry(
            key: 'hours-attendance-report',
            category: $hours,
            title: 'Employee Hours & Attendance report',
            subtitle: 'On-demand generated report (not a retained export)',
            datedAt: $this->settings->now(),
            status: 'On demand',
            sourceUrl: route('reports.show', 'employee-attendance'),
            previewUrl: route('documents.hours-attendance.preview'),
            downloadUrl: route('documents.hours-attendance.pdf'),
        ));

        return $rows;
    }

    /**
     * Index retained Incident PDF snapshots (current + superseded).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, true>> incident_id => [document_key => true]
     */
    private function appendRetainedIncidentArtifacts(Collection $rows): array
    {
        $covered = [];
        $incidentMorph = (new Incident)->getMorphClass();

        $artifacts = RetainedDocumentArtifact::query()
            ->where('source_type', $incidentMorph)
            ->whereIn('document_key', [
                RetainedDocumentKey::IncidentSubmitted->value,
                RetainedDocumentKey::IncidentFinal->value,
            ])
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get();

        if ($artifacts->isEmpty()) {
            return $covered;
        }

        $incidents = Incident::query()
            ->with(['client:id,first_name,last_name', 'reporter:id,first_name,last_name'])
            ->whereIn('id', $artifacts->pluck('source_id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($artifacts as $artifact) {
            $source = $incidents->get($artifact->source_id);
            if ($source === null) {
                continue;
            }

            $key = $artifact->document_key instanceof RetainedDocumentKey
                ? $artifact->document_key
                : RetainedDocumentKey::from((string) $artifact->document_key);

            $covered[$source->id][$key->value] = true;

            $statusParts = ['Retained'];
            $statusParts[] = $artifact->is_current
                ? 'current v'.$artifact->version
                : 'superseded v'.$artifact->version;

            $previewUrl = $artifact->is_current
                ? match ($key) {
                    RetainedDocumentKey::IncidentSubmitted => route('documents.incident-submitted.preview', $source),
                    RetainedDocumentKey::IncidentFinal => $source->status === IncidentStatus::Closed
                        ? route('documents.incident-final.preview', $source)
                        : route('documents.retained.preview', $artifact),
                }
                : route('documents.retained.preview', $artifact);

            $downloadUrl = $artifact->is_current
                ? match ($key) {
                    RetainedDocumentKey::IncidentSubmitted => route('documents.incident-submitted.pdf', $source),
                    RetainedDocumentKey::IncidentFinal => $source->status === IncidentStatus::Closed
                        ? route('documents.incident-final.pdf', $source)
                        : route('documents.retained.download', $artifact),
                }
                : route('documents.retained.download', $artifact);

            $rows->push($this->entry(
                key: 'retained:'.$artifact->id,
                category: $key->adminCategory(),
                title: $artifact->title,
                subtitle: $artifact->subtitle ?? $source->client?->full_name,
                datedAt: $artifact->generated_at,
                employeeName: $source->reporter?->full_name,
                employeeId: $source->reporter_employee_id,
                clientName: $source->client?->full_name,
                clientId: $source->client_id,
                status: implode(' · ', $statusParts),
                sourceUrl: route('incidents.show', $source),
                previewUrl: $previewUrl,
                downloadUrl: $downloadUrl,
            ));
        }

        return $covered;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return bool
     */
    private function matches(array $row, array $filters): bool
    {
        if ($filters['category'] !== '' && $row['category'] !== $filters['category']) {
            return false;
        }

        if ($filters['origin'] !== '' && $row['origin'] !== $filters['origin']) {
            return false;
        }

        if ($filters['source_area'] !== '' && $row['source_area'] !== $filters['source_area']) {
            return false;
        }

        if ($filters['from'] !== '') {
            $from = Carbon::parse($filters['from'])->startOfDay();
            if (Carbon::parse((string) $row['dated_at'])->lt($from)) {
                return false;
            }
        }

        if ($filters['to'] !== '') {
            $to = Carbon::parse($filters['to'])->endOfDay();
            if (Carbon::parse((string) $row['dated_at'])->gt($to)) {
                return false;
            }
        }

        if ($filters['search'] !== '') {
            $haystack = strtolower(implode(' ', array_filter([
                $row['title'],
                $row['subtitle'],
                $row['employee_name'],
                $row['client_name'],
                $row['category_label'],
                $row['source_area_label'],
                $row['status'],
            ])));

            if (! str_contains($haystack, strtolower($filters['search']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(
        string $key,
        AdminDocumentCategory $category,
        string $title,
        ?string $subtitle,
        mixed $datedAt,
        ?string $sourceUrl,
        ?string $previewUrl,
        ?string $downloadUrl,
        ?string $employeeName = null,
        ?int $employeeId = null,
        ?string $clientName = null,
        ?int $clientId = null,
        ?string $status = null,
    ): array {
        $instant = $datedAt instanceof Carbon
            ? $datedAt
            : Carbon::parse((string) $datedAt);

        return [
            'key' => $key,
            'category' => $category->value,
            'category_label' => $category->label(),
            'origin' => $category->origin()->value,
            'origin_label' => $category->origin()->label(),
            'source_area' => $category->sourceArea(),
            'source_area_label' => $category->sourceAreaLabel(),
            'title' => $title,
            'subtitle' => $subtitle,
            'status' => $status,
            'employee_name' => $employeeName,
            'employee_id' => $employeeId,
            'client_name' => $clientName,
            'client_id' => $clientId,
            'dated_at' => $instant->utc()->toIso8601String(),
            'dated_at_label' => $this->settings->formatDateTime($instant),
            'source_url' => $sourceUrl,
            'preview_url' => $previewUrl,
            'download_url' => $downloadUrl,
            'ownership_note' => 'Source-owned. Admin Documents is discovery only.',
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function filterOptions(): array
    {
        return [
            'categories' => array_map(
                fn (AdminDocumentCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ],
                AdminDocumentCategory::cases(),
            ),
            'origins' => array_map(
                fn (AdminDocumentOrigin $origin): array => [
                    'value' => $origin->value,
                    'label' => $origin->label(),
                ],
                AdminDocumentOrigin::cases(),
            ),
            'source_areas' => [
                ['value' => 'hr_compliance', 'label' => 'HR / Compliance'],
                ['value' => 'incidents', 'label' => 'Incidents'],
                ['value' => 'visits', 'label' => 'Visits'],
                ['value' => 'attendance_hours', 'label' => 'Attendance / Hours'],
            ],
        ];
    }

    public function streamCredentialDocument(User $actor, EmployeeCredential $credential)
    {
        abort_unless($actor->isAdmin(), 403);
        Gate::forUser($actor)->authorize('view', $credential->employee);
        abort_unless(filled($credential->document_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($credential->document_path);
    }

    public function streamTrainingDocument(User $actor, EmployeeTraining $training)
    {
        abort_unless($actor->isAdmin(), 403);
        Gate::forUser($actor)->authorize('view', $training->employee);
        abort_unless(filled($training->document_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($training->document_path);
    }
}
