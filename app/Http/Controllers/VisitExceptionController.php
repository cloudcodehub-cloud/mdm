<?php

namespace App\Http\Controllers;

use App\Enums\VisitExceptionStatus;
use App\Enums\VisitExceptionType;
use App\Http\Requests\ResolveVisitExceptionRequest;
use App\Http\Requests\ReviewVisitExceptionRequest;
use App\Models\VisitException;
use App\Services\VisitExceptionService;
use App\Support\DirectoryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitExceptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', VisitException::class);

        $filters = [
            'status' => $request->string('status')->value(),
            'type' => $request->string('type')->value(),
        ];

        $status = VisitExceptionStatus::tryFrom($filters['status']);
        $type = VisitExceptionType::tryFrom($filters['type']);

        $exceptions = VisitException::query()
            ->visibleTo($user)
            ->with(['visit.client', 'visit.employee', 'visitTask', 'reviewedBy', 'resolvedBy'])
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->unresolved(),
            )
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('visit-exceptions/index', [
            'exceptions' => [
                'data' => DirectoryPresenter::visitExceptions($exceptions->getCollection()),
                'meta' => [
                    'current_page' => $exceptions->currentPage(),
                    'last_page' => $exceptions->lastPage(),
                    'from' => $exceptions->firstItem(),
                    'to' => $exceptions->lastItem(),
                    'total' => $exceptions->total(),
                ],
                'links' => [
                    'prev' => $exceptions->previousPageUrl(),
                    'next' => $exceptions->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, VisitException $visitException): Response
    {
        $this->authorize('view', $visitException);

        $visitException->load([
            'visit.client',
            'visit.employee',
            'visit.scheduledVisit.shiftTemplate',
            'visitTask',
            'reviewedBy',
            'resolvedBy',
        ]);

        $user = $request->user();

        return Inertia::render('visit-exceptions/show', [
            'exception' => DirectoryPresenter::visitException($visitException),
            'can' => [
                'review' => $user?->can('review', $visitException) ?? false,
                'resolve' => $user?->can('resolve', $visitException) ?? false,
            ],
        ]);
    }

    public function review(ReviewVisitExceptionRequest $request, VisitException $visitException, VisitExceptionService $exceptions): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $exceptions->review($visitException, $user, $request->validated('review_notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exception marked reviewed.')]);

        return redirect()->route('visit-exceptions.show', $visitException);
    }

    public function resolve(ResolveVisitExceptionRequest $request, VisitException $visitException, VisitExceptionService $exceptions): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $exceptions->resolve($visitException, $user, $request->validated('resolution_notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exception resolved.')]);

        return redirect()->route('visit-exceptions.show', $visitException);
    }
}
