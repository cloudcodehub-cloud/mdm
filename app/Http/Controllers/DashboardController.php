<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboards): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $user->load('employee');

        return Inertia::render('dashboard', [
            'dashboard' => $dashboards->forUser($user),
        ]);
    }
}
