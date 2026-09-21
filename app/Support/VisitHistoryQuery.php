<?php

namespace App\Support;

use App\Enums\ScheduledVisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VisitHistoryQuery
{
    /**
     * @return array{
     *     phase: string,
     *     from: string,
     *     to: string,
     *     service_date: string,
     *     client_id: string,
     *     employee_id: string,
     *     supervisor_id: string,
     *     service_type: string,
     *     status: string
     * }
     */
    public static function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->value();
        $phase = $request->string('phase')->trim()->value();
        $valid = ['upcoming', 'in_progress', 'completed', 'cancelled', 'all'];

        if ($phase === '' && $status === '') {
            $phase = 'upcoming';
        }

        if ($phase !== '' && ! in_array($phase, $valid, true)) {
            $phase = 'upcoming';
        }

        return [
            'phase' => $phase,
            'from' => $request->string('from')->trim()->value(),
            'to' => $request->string('to')->trim()->value(),
            'service_date' => $request->string('service_date')->trim()->value(),
            'client_id' => $request->string('client_id')->value(),
            'employee_id' => $request->string('employee_id')->value(),
            'supervisor_id' => $request->string('supervisor_id')->value(),
            'service_type' => $request->string('service_type')->trim()->value(),
            'status' => $status,
        ];
    }

    /**
     * @param  Builder<\App\Models\ScheduledVisit>  $query
     * @param  array<string, string>  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        $legacyDate = $filters['service_date'] ?? '';

        if ($from !== '' && self::isDate($from)) {
            $query->whereDate('service_date', '>=', $from);
        }

        if ($to !== '' && self::isDate($to)) {
            $query->whereDate('service_date', '<=', $to);
        }

        if ($from === '' && $to === '' && $legacyDate !== '' && self::isDate($legacyDate)) {
            $query->whereDate('service_date', $legacyDate);
        }

        if (($filters['client_id'] ?? '') !== '' && ctype_digit($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (($filters['employee_id'] ?? '') !== '' && ctype_digit($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (($filters['supervisor_id'] ?? '') !== '' && ctype_digit($filters['supervisor_id'])) {
            $query->where('supervisor_id', (int) $filters['supervisor_id']);
        }

        if (($filters['service_type'] ?? '') !== '') {
            $query->where('service_type', $filters['service_type']);
        }

        $status = $filters['status'] ?? '';

        if ($status !== '' && ScheduledVisitStatus::tryFrom($status)) {
            $query->where('status', $status);

            return;
        }

        $phase = $filters['phase'] ?? '';

        $mapped = match ($phase) {
            'upcoming' => ScheduledVisitStatus::Scheduled->value,
            'in_progress' => ScheduledVisitStatus::InProgress->value,
            'completed' => ScheduledVisitStatus::Completed->value,
            'cancelled' => ScheduledVisitStatus::Cancelled->value,
            default => null,
        };

        if ($mapped !== null) {
            $query->where('status', $mapped);
        }
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    public static function queryString(array $filters): array
    {
        return array_filter(
            $filters,
            fn (string $value): bool => $value !== '',
        );
    }

    public static function prefersNewestFirst(array $filters): bool
    {
        $phase = $filters['phase'] ?? '';
        $status = $filters['status'] ?? '';

        return in_array($phase, ['completed', 'cancelled', 'all'], true)
            || in_array($status, [
                ScheduledVisitStatus::Completed->value,
                ScheduledVisitStatus::Cancelled->value,
            ], true);
    }

    private static function isDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
        } catch (\Throwable) {
            return false;
        }
    }
}
