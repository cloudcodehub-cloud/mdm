<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCredential;
use App\Services\ComplianceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    public function __invoke(Request $request, ComplianceService $compliance): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorize('viewAny', EmployeeCredential::class);

        return Inertia::render('compliance/index', $compliance->forUser($user));
    }
}
