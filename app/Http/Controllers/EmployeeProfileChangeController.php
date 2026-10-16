<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreEmployeeProfileChangeRequest;
use App\Models\EmployeeProfileChangeRequest;
use App\Services\EmployeeProfileChangeService;
use App\Services\SchedulingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeProfileChangeController extends Controller
{
    public function mine(Request $request, EmployeeProfileChangeService $changes): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isDsp() && $user->employee !== null, 403);

        $employee = $user->employee;
        $employee->load(['profileChangeRequests' => fn ($query) => $query->limit(20)]);

        return Inertia::render('profile-changes/mine', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'phone' => $employee->phone,
                'cell_phone' => $employee->cell_phone,
                'home_phone' => $employee->home_phone,
                'alternate_phone' => $employee->alternate_phone,
                'address_line_1' => $employee->address_line_1,
                'address_line_2' => $employee->address_line_2,
                'city' => $employee->city,
                'state' => $employee->state,
                'postal_code' => $employee->postal_code,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_relationship' => $employee->emergency_contact_relationship,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
            'requests' => $employee->profileChangeRequests->map(fn (EmployeeProfileChangeRequest $row): array => [
                'id' => $row->id,
                'status' => $row->status->value,
                'reason' => $row->reason,
                'submitted_at' => $row->submitted_at?->toDateTimeString(),
                'review_note' => $row->review_note,
                'fields' => $changes->serializeForReview($row),
            ])->values()->all(),
            'has_pending' => $employee->profileChangeRequests()->pending()->exists(),
        ]);
    }

    public function store(
        StoreEmployeeProfileChangeRequest $request,
        EmployeeProfileChangeService $changes,
        SchedulingNotificationService $notifications,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null && $user->employee !== null, 403);

        $record = $changes->submit($user->employee, $user, $request->validated());
        $notifications->profileChangeSubmitted($record);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Personal information change submitted for review. Current profile values stay authoritative until approved.'),
        ]);

        return redirect()->route('my-profile-changes.index');
    }

    public function index(Request $request, EmployeeProfileChangeService $changes): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', EmployeeProfileChangeRequest::class);

        $rows = EmployeeProfileChangeRequest::query()
            ->with(['employee', 'requestedBy', 'reviewedBy'])
            ->when(
                $user->isSupervisor() && $user->employee,
                fn ($query) => $query->whereHas(
                    'employee',
                    fn ($builder) => $builder->where('supervisor_id', $user->employee?->id),
                ),
            )
            ->when(
                $user->isDsp() && $user->employee,
                fn ($query) => $query->where('employee_id', $user->employee->id),
            )
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('submitted_at')
            ->limit(80)
            ->get();

        return Inertia::render('profile-changes/index', [
            'requests' => $rows->map(fn (EmployeeProfileChangeRequest $row): array => [
                'id' => $row->id,
                'employee_name' => $row->employee->full_name,
                'status' => $row->status->value,
                'reason' => $row->reason,
                'requester' => $row->requestedBy->name,
                'submitted_at' => $row->submitted_at?->toDateTimeString(),
                'review_note' => $row->review_note,
                'fields' => $changes->serializeForReview($row),
            ])->values()->all(),
            'can_review' => $user->isAdmin() || $user->isSupervisor(),
        ]);
    }

    public function approve(
        ReviewRequest $request,
        EmployeeProfileChangeRequest $profile_change,
        EmployeeProfileChangeService $changes,
    ): RedirectResponse {
        $this->authorize('review', $profile_change);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $changes->approve($profile_change, $user, $request->string('review_note')->value() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Personal information change approved.')]);

        return back();
    }

    public function reject(
        ReviewRequest $request,
        EmployeeProfileChangeRequest $profile_change,
        EmployeeProfileChangeService $changes,
    ): RedirectResponse {
        $this->authorize('review', $profile_change);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $changes->reject($profile_change, $user, $request->string('review_note')->value() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Personal information change rejected.')]);

        return back();
    }
}
