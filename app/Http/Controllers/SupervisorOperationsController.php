<?php

namespace App\Http\Controllers;

use App\Models\VisitException;
use App\Services\SupervisorOperationsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorOperationsController extends Controller
{
    public function __invoke(Request $request, SupervisorOperationsService $operations): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', VisitException::class);

        return Inertia::render('operations/index', [
            'operations' => $operations->forUser($user),
        ]);
    }
}
