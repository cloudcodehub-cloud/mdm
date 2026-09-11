<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\DspAvailabilityRequest;
use App\Models\EmployeeTimeOff;
use App\Services\DspAvailabilityService;
use App\Services\SchedulingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', DspAvailabilityRequest::class);

        $requests = DspAvailabilityRequest::query()
            ->with(['employee', 'requestedBy', 'reviewedBy'])
            ->when(
                $user->isSupervisor() && $user->employee,
                fn ($query) => $query->whereHas('employee', fn ($builder) => $builder->where('supervisor_id', $user->employee?->id)),
            )
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('submitted_at')
            ->limit(80)
            ->get();

        $timeOff = EmployeeTimeOff::query()
            ->with(['employee', 'requestedBy', 'reviewedBy'])
            ->when(
                $user->isSupervisor() && $user->employee,
                fn ($query) => $query->whereHas('employee', fn ($builder) => $builder->where('supervisor_id', $user->employee?->id)),
            )
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('submitted_at')
            ->limit(80)
            ->get();

        return Inertia::render('availability/requests', [
            'requests' => $requests->map(fn (DspAvailabilityRequest $row): array => [
                'id' => $row->id,
                'dsp_name' => $row->employee->full_name,
                'type' => $row->type->value,
                'effective_on' => $row->effective_on->toDateString(),
                'reason' => $row->reason,
                'payload' => $row->payload,
                'status' => $row->status->value,
                'requester' => $row->requestedBy->name,
                'submitted_at' => $row->submitted_at->toDateTimeString(),
                'reviewer' => $row->reviewedBy?->name,
                'reviewed_at' => $row->reviewed_at?->toDateTimeString(),
                'review_note' => $row->review_note,
            ])->values()->all(),
            'time_off' => $timeOff->map(fn (EmployeeTimeOff $row): array => [
                'id' => $row->id,
                'dsp_name' => $row->employee->full_name,
                'starts_on' => $row->starts_on->toDateString(),
                'ends_on' => $row->ends_on->toDateString(),
                'reason' => $row->reason,
                'status' => $row->status->value,
                'requester' => $row->requestedBy->name,
                'review_note' => $row->review_note,
            ])->values()->all(),
        ]);
    }

    public function approve(
        ReviewRequest $request,
        DspAvailabilityRequest $availability_request,
        DspAvailabilityService $availability,
        SchedulingNotificationService $notifications,
    ): RedirectResponse {
        $this->authorize('review', $availability_request);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $affected = $availability->approveRequest(
            $availability_request,
            $user,
            $request->string('review_note')->value() ?: null,
        );

        foreach ($affected as $visit) {
            $notifications->coverageAttention($visit);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($affected) > 0
                ? __('Request approved. :count visit(s) flagged for attention.', ['count' => count($affected)])
                : __('Request approved.'),
        ]);

        return back();
    }

    public function reject(
        ReviewRequest $request,
        DspAvailabilityRequest $availability_request,
        DspAvailabilityService $availability,
    ): RedirectResponse {
        $this->authorize('review', $availability_request);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $availability->rejectRequest($availability_request, $user, $request->string('review_note')->value() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request rejected.')]);

        return back();
    }

    public function approveTimeOff(
        ReviewRequest $request,
        EmployeeTimeOff $time_off,
        DspAvailabilityService $availability,
        SchedulingNotificationService $notifications,
    ): RedirectResponse {
        $this->authorize('review', $time_off);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $affected = $availability->approveTimeOff($time_off, $user, $request->string('review_note')->value() ?: null);

        foreach ($affected as $visit) {
            $notifications->coverageAttention($visit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off approved.')]);

        return back();
    }

    public function rejectTimeOff(
        ReviewRequest $request,
        EmployeeTimeOff $time_off,
        DspAvailabilityService $availability,
    ): RedirectResponse {
        $this->authorize('review', $time_off);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $availability->rejectTimeOff($time_off, $user, $request->string('review_note')->value() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off rejected.')]);

        return back();
    }
}
