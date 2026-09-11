<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\ScheduledVisit;
use App\Services\AttendanceService;
use App\Services\SettingsService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance, SettingsService $settings): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', ScheduledVisit::class);

        $today = $settings->today();
        $defaultFrom = $settings->localNow()->subDays(6)->toDateString();

        $filters = [
            'from' => $request->string('from')->trim()->value() ?: $defaultFrom,
            'to' => $request->string('to')->trim()->value() ?: $today,
            'employee_id' => $request->string('employee_id')->value(),
            'client_id' => $request->string('client_id')->value(),
            'supervisor_id' => $request->string('supervisor_id')->value(),
            'status' => $request->string('status')->value(),
        ];

        $records = $attendance->page(
            $user,
            $filters,
            max(1, (int) $request->integer('page', 1)),
            $request->url(),
            $request->query(),
        );

        return Inertia::render('attendance/index', [
            'records' => $records,
            'filters' => $filters,
            'pending_corrections' => $attendance->pendingCorrections($user),
            'clients' => DirectoryPresenter::clientFilterOptions($user),
            'dsps' => DirectoryPresenter::dspFilterOptions($user),
            'supervisors' => $user->isAdmin() ? DirectoryPresenter::supervisorOptions() : [],
            'statuses' => array_map(
                fn (AttendanceStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                AttendanceStatus::cases(),
            ),
            'can' => [
                'review_corrections' => $user->isAdmin(),
                'filter_employees' => ! $user->isDsp(),
            ],
        ]);
    }

    public function show(Request $request, ScheduledVisit $scheduledVisit, AttendanceService $attendance): Response
    {
        $this->authorize('view', $scheduledVisit);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $record = $attendance->detail($scheduledVisit, $user);

        return Inertia::render('attendance/show', [
            'record' => $record,
            'can' => [
                'request_correction' => $record['can_request_correction'],
                'review_corrections' => $user->isAdmin(),
            ],
        ]);
    }
}
