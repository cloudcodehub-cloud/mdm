<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use App\Support\OperationalReport;

class DownloadReportRequest extends ViewReportRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $report = $this->route('report');
        $type = $report instanceof ReportType
            ? $report
            : ReportType::tryFrom((string) $report);

        if ($type === ReportType::PayrollHours) {
            return $user->can('exportPayroll', OperationalReport::class);
        }

        return $user->can('export', OperationalReport::class);
    }
}
