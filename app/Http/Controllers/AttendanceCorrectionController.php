<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewAttendanceCorrectionRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AttendanceCorrectionController extends Controller
{
    public function store(
        StoreAttendanceCorrectionRequest $request,
        ScheduledVisit $scheduledVisit,
        AttendanceCorrectionService $corrections,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $payload = $request->validated();
        // MDM 05-02 — surface illegal coordinate manufacturing attempts even if not validated.
        foreach (['latitude', 'longitude', 'requested_clock_in_latitude', 'requested_clock_out_latitude'] as $forbidden) {
            if ($request->filled($forbidden)) {
                $payload[$forbidden] = $request->input($forbidden);
            }
        }

        $corrections->submit($scheduledVisit, $user, $payload);

        $message = $user->isAdmin()
            ? __('Visit correction applied. Original EVV evidence was preserved.')
            : __('Correction request submitted for review. Original EVV evidence was preserved.');

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('attendance.show', $scheduledVisit);
    }

    public function approve(
        ReviewAttendanceCorrectionRequest $request,
        AttendanceCorrection $attendanceCorrection,
        AttendanceCorrectionService $corrections,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $corrections->approve($attendanceCorrection, $user, $request->validated('review_note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Correction approved. Original EVV evidence was preserved.')]);

        return redirect()->route('attendance.show', $attendanceCorrection->scheduled_visit_id);
    }

    public function reject(
        ReviewAttendanceCorrectionRequest $request,
        AttendanceCorrection $attendanceCorrection,
        AttendanceCorrectionService $corrections,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $corrections->reject($attendanceCorrection, $user, $request->validated('review_note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Correction request rejected.')]);

        return redirect()->route('attendance.show', $attendanceCorrection->scheduled_visit_id);
    }
}
