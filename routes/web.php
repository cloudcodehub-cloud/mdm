<?php

use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('employees', ComingSoonController::class)->name('employees.index');
    Route::get('clients', ComingSoonController::class)->name('clients.index');
    Route::get('supervisors', ComingSoonController::class)->name('supervisors.index');
    Route::get('attendance', ComingSoonController::class)->name('attendance.index');
    Route::get('compliance', ComingSoonController::class)->name('compliance.index');
    Route::get('reports', ComingSoonController::class)->name('reports.index');
    Route::get('messages', ComingSoonController::class)->name('messages.index');
});

require __DIR__.'/settings.php';
