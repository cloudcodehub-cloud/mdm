<?php

namespace App\Http\Controllers;

use App\Http\Requests\DspAvailabilityRequestForm;
use App\Http\Requests\OverrideWeeklyAvailabilityRequest;
use App\Http\Requests\TimeOffRequest;
use App\Models\DspAvailabilityRequest;
use App\Models\EmployeeTimeOff;
use App\Services\DspAvailabilityService;
use App\Services\SchedulingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DspAvailabilityController extends Controller
{
    public function index(Request $request, DspAvailabilityService $availability): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isDsp() && $user->employee !== null, 403);

        $employee = $user->employee;
        $employee->load(['weeklyAvailabilities', 'availabilityExceptions', 'availabilityRequests.reviewedBy', 'timeOff.reviewedBy']);

        return Inertia::render('availability/mine', [
            'weekly' => $availability->serializeWeekly($employee),
            'exceptions' => $employee->availabilityExceptions->map(fn ($row): array => [
                'id' => $row->id,
                'exception_date' => $row->exception_date->toDateString(),
                'is_available' => $row->is_available,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
                'note' => $row->note,
            ])->values()->all(),
            'requests' => $employee->availabilityRequests->map(fn (DspAvailabilityRequest $row): array => $this->requestPayload($row))->values()->all(),
            'time_off' => $employee->timeOff->map(fn (EmployeeTimeOff $row): array => $this->timeOffPayload($row))->values()->all(),
        ]);
    }

    public function store(DspAvailabilityRequestForm $request, DspAvailabilityService $availability, SchedulingNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isDsp() && $user->employee !== null, 403);

        $record = $availability->submitRequest($user->employee, $user, $request->validated());
        $notifications->availabilitySubmitted($record);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability change submitted for review.')]);

        return redirect()->route('my-availability.index');
    }

    public function storeTimeOff(TimeOffRequest $request, DspAvailabilityService $availability, SchedulingNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isDsp() && $user->employee !== null, 403);

        $record = $availability->submitTimeOff($user->employee, $user, $request->validated());

        if ($record->isPending()) {
            $notifications->timeOffSubmitted($record);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time-off request submitted.')]);

        return redirect()->route('my-availability.index');
    }

    public function overrideWeekly(OverrideWeeklyAvailabilityRequest $request, DspAvailabilityService $availability): RedirectResponse
    {
        $employee = $request->employee();
        $availability->replaceWeekly($employee, $request->validated('days') ?? []);
        $affected = $availability->flagVisitsAffectedByWeekly($employee, now()->toDateString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($affected) > 0
                ? __('Availability updated. :count scheduled visit(s) flagged for attention.', ['count' => count($affected)])
                : __('Availability updated.'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(DspAvailabilityRequest $row): array
    {
        return [
            'id' => $row->id,
            'type' => $row->type->value,
            'effective_on' => $row->effective_on->toDateString(),
            'reason' => $row->reason,
            'status' => $row->status->value,
            'submitted_at' => $row->submitted_at->toDateTimeString(),
            'reviewed_at' => $row->reviewed_at?->toDateTimeString(),
            'review_note' => $row->review_note,
            'reviewer' => $row->reviewedBy?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeOffPayload(EmployeeTimeOff $row): array
    {
        return [
            'id' => $row->id,
            'starts_on' => $row->starts_on->toDateString(),
            'ends_on' => $row->ends_on->toDateString(),
            'reason' => $row->reason,
            'status' => $row->status->value,
            'review_note' => $row->review_note,
        ];
    }
}
