<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ScheduledVisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Support\DirectoryPresenter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceService
{
    public function __construct(
        private SettingsService $settings,
        private AttendanceStatusService $statuses,
    ) {}

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @param  array<string, mixed>  $query
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, from: int|null, to: int|null, total: int}, links: array{prev: string|null, next: string|null}}
     */
    public function page(User $user, array $filters, int $page, string $url, array $query, int $perPage = 20): array
    {
        $records = $this->records($user, $filters);

        $page = max(1, $page);
        $total = $records->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $items = [];

        foreach ($records->forPage($page, $perPage) as $record) {
            $items[] = $record;
        }

        $from = $total === 0 ? null : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? null : min($page * $perPage, $total);
        $queryWithoutPage = $query;
        unset($queryWithoutPage['page']);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'total' => $total,
            ],
            'links' => [
                'prev' => $page > 1 ? $this->pageUrl($url, $queryWithoutPage, $page - 1) : null,
                'next' => $page < $lastPage ? $this->pageUrl($url, $queryWithoutPage, $page + 1) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(ScheduledVisit $scheduledVisit, User $user): array
    {
        $scheduledVisit->load([
            'client',
            'employee.supervisor',
            'supervisor',
            'shiftTemplate',
            'visit.exceptions',
            'attendanceCorrections.requestedBy',
            'attendanceCorrections.reviewedBy',
        ]);

        return $this->serialize($scheduledVisit, $user, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingCorrections(User $user): array
    {
        if (! $user->isAdmin()) {
            return [];
        }

        $corrections = AttendanceCorrection::query()
            ->pending()
            ->with(['scheduledVisit.client', 'scheduledVisit.employee', 'requestedBy', 'visit'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (AttendanceCorrection $correction): array => $this->serializeCorrection($correction))
            ->values()
            ->all();

        return array_values($corrections);
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function records(User $user, array $filters): Collection
    {
        $status = AttendanceStatus::tryFrom($filters['status']);
        $rows = [];

        foreach ($this->query($user, $filters)
            ->with([
                'client',
                'employee.supervisor',
                'supervisor',
                'shiftTemplate',
                'visit.exceptions',
                'attendanceCorrections.requestedBy',
                'attendanceCorrections.reviewedBy',
            ])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get() as $visit) {
            $row = $this->serialize($visit, $user);

            if ($status !== null && $row['status'] !== $status->value) {
                continue;
            }

            $rows[] = $row;
        }

        return collect($rows);
    }

    /**
     * @param  array{from: string, to: string, employee_id: string, client_id: string, supervisor_id: string, status: string}  $filters
     * @return Builder<ScheduledVisit>
     */
    public function query(User $user, array $filters): Builder
    {
        $from = $filters['from'] !== '' ? $filters['from'] : $this->settings->today();
        $to = $filters['to'] !== '' ? $filters['to'] : $from;

        return ScheduledVisit::query()
            ->visibleTo($user)
            ->where('status', '!=', ScheduledVisitStatus::Cancelled)
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->when(
                $filters['employee_id'] !== '' && ctype_digit($filters['employee_id']),
                fn (Builder $query) => $query->where('employee_id', (int) $filters['employee_id']),
            )
            ->when(
                $filters['client_id'] !== '' && ctype_digit($filters['client_id']),
                fn (Builder $query) => $query->where('client_id', (int) $filters['client_id']),
            )
            ->when(
                $user->isAdmin() && $filters['supervisor_id'] !== '' && ctype_digit($filters['supervisor_id']),
                function (Builder $query) use ($filters): void {
                    $supervisorId = (int) $filters['supervisor_id'];
                    $query->where(function (Builder $builder) use ($supervisorId): void {
                        $builder->where('supervisor_id', $supervisorId)
                            ->orWhereHas(
                                'employee',
                                fn (Builder $employee): Builder => $employee->where('supervisor_id', $supervisorId),
                            );
                    });
                },
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ScheduledVisit $scheduledVisit, User $user, bool $detailed = false): array
    {
        $status = $this->statuses->statusFor($scheduledVisit);
        $visit = $scheduledVisit->visit;
        $effective = $this->statuses->effectiveTimes($scheduledVisit);
        $pending = $this->statuses->pendingCorrection($scheduledVisit);
        $approved = $this->statuses->approvedAdjustment($scheduledVisit);

        $row = [
            'id' => $scheduledVisit->id,
            'visit_id' => $visit?->id,
            'service_date' => $scheduledVisit->service_date->toDateString(),
            'service_type' => $scheduledVisit->service_type,
            'employee' => [
                'id' => $scheduledVisit->employee->id,
                'name' => $scheduledVisit->employee->full_name,
            ],
            'client' => [
                'id' => $scheduledVisit->client->id,
                'name' => $scheduledVisit->client->full_name,
            ],
            'supervisor_name' => $this->supervisorName($scheduledVisit),
            'scheduled_time' => DirectoryPresenter::visitTimeLabel($scheduledVisit),
            'original_clock_in' => $this->stamp($visit?->clocked_in_at),
            'original_clock_out' => $this->stamp($visit?->clocked_out_at),
            'effective_clock_in' => $this->stamp($effective['start']),
            'effective_clock_out' => $this->stamp($effective['end']),
            'worked_duration' => $this->durationLabel($effective['start'], $effective['end']),
            'original_duration' => $this->durationLabel($visit?->clocked_in_at, $visit?->clocked_out_at),
            'status' => $status->value,
            'status_label' => $status->label(),
            'has_gps_issue' => $this->statuses->hasGpsIssue($visit),
            'gps_label' => $visit === null ? null : DirectoryPresenter::locationStatusLabel($visit),
            'has_exception' => $this->statuses->hasException($visit),
            'has_adjustment' => $approved !== null || $pending !== null,
            'adjustment_label' => $this->adjustmentLabel($approved, $pending),
            'is_adjusted' => $effective['adjusted'],
            'can_request_correction' => $user->can('create', [AttendanceCorrection::class, $scheduledVisit])
                && $visit !== null
                && $pending === null,
            'pending_correction_id' => $pending?->id,
        ];

        if (! $detailed) {
            return $row;
        }

        $row['corrections'] = $scheduledVisit->attendanceCorrections
            ->map(fn (AttendanceCorrection $correction): array => $this->serializeCorrection($correction))
            ->values()
            ->all();
        $row['clock_in_input'] = $this->settings->datetimeLocalValue($visit?->clocked_in_at);
        $row['clock_out_input'] = $this->settings->datetimeLocalValue($visit?->clocked_out_at);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCorrection(AttendanceCorrection $correction): array
    {
        $scheduled = $correction->relationLoaded('scheduledVisit') ? $correction->scheduledVisit : null;

        return [
            'id' => $correction->id,
            'scheduled_visit_id' => $correction->scheduled_visit_id,
            'visit_id' => $correction->visit_id,
            'status' => $correction->status->value,
            'status_label' => $correction->status->label(),
            'reason' => $correction->reason,
            'note' => $correction->note,
            'review_note' => $correction->review_note,
            'requested_by_name' => $correction->requestedBy->name,
            'reviewed_by_name' => $correction->reviewedBy?->name,
            'reviewed_at' => $this->stamp($correction->reviewed_at),
            'created_at' => $this->stamp($correction->created_at),
            'original_clock_in' => $this->stamp($correction->original_clocked_in_at),
            'original_clock_out' => $this->stamp($correction->original_clocked_out_at),
            'requested_clock_in' => $this->stamp($correction->requested_clocked_in_at),
            'requested_clock_out' => $this->stamp($correction->requested_clocked_out_at),
            'employee_name' => $scheduled?->employee?->full_name,
            'client_name' => $scheduled?->client?->full_name,
        ];
    }

    private function supervisorName(ScheduledVisit $scheduledVisit): ?string
    {
        $ofRecord = $scheduledVisit->getRelation('supervisor');

        if ($ofRecord instanceof Employee) {
            return $ofRecord->full_name;
        }

        $assigned = $scheduledVisit->employee->getRelation('supervisor');

        return $assigned instanceof Employee ? $assigned->full_name : null;
    }

    private function stamp(?CarbonInterface $value): ?string
    {
        return $value === null ? null : $this->settings->formatDateTime($value);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function pageUrl(string $url, array $query, int $page): string
    {
        $query['page'] = $page;

        return $url.'?'.http_build_query($query);
    }

    private function durationLabel(?CarbonInterface $start, ?CarbonInterface $end): ?string
    {
        if ($start === null || $end === null) {
            return null;
        }

        $minutes = (int) $start->diffInMinutes($end);
        $hours = intdiv(max(0, $minutes), 60);
        $remainder = max(0, $minutes) % 60;

        return sprintf('%dh %02dm', $hours, $remainder);
    }

    private function adjustmentLabel(?AttendanceCorrection $approved, ?AttendanceCorrection $pending): ?string
    {
        if ($approved !== null) {
            return 'Adjusted';
        }

        if ($pending !== null) {
            return 'Correction pending';
        }

        return null;
    }
}
