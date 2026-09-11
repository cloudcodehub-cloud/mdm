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

        $corrections->submit($scheduledVisit, $user, [
            'requested_clocked_in_at' => $request->string('requested_clocked_in_at')->value() ?: null,
            'requested_clocked_out_at' => $request->string('requested_clocked_out_at')->value() ?: null,
            'reason' => $request->string('reason')->value(),
            'note' => $request->string('note')->value() !== '' ? $request->string('note')->value() : null,
        ]);

        $message = $user->isAdmin()
            ? __('Attendance correction applied. Original clock times were preserved.')
            : __('Correction request submitted for admin review.');

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Correction approved. Original clock times were preserved.')]);

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
