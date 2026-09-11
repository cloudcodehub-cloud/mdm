<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Http\Requests\DownloadReportRequest;
use App\Http\Requests\ViewReportRequest;
use App\Services\ReportService;
use App\Services\SettingsService;
use App\Support\OperationalReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports, SettingsService $settings): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', OperationalReport::class);

        return Inertia::render('reports/index', [
            'reports' => $reports->catalog(),
            'timezone' => $settings->timezone(),
        ]);
    }

    public function show(
        ViewReportRequest $request,
        ReportType $report,
        ReportService $reports,
        SettingsService $settings,
    ): Response {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $filters = $request->filters($report, $settings);

        return Inertia::render('reports/show', $reports->page(
            $user,
            $report,
            $filters,
            max(1, (int) $request->integer('page', 1)),
            $request->url(),
            $request->query(),
        ));
    }

    public function download(
        DownloadReportRequest $request,
        ReportType $report,
        ReportService $reports,
        SettingsService $settings,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $reports->download($user, $report, $request->filters($report, $settings));
    }
}
