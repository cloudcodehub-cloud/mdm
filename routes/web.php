<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDspAssignmentController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ScheduledVisitController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SupervisorOperationsController;
use App\Http\Controllers\VisitClockInController;
use App\Http\Controllers\VisitClockOutController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitExceptionController;
use App\Http\Controllers\VisitTaskController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::post('clients/{client}/assignments', [ClientDspAssignmentController::class, 'store'])->name('clients.assignments.store');
    Route::patch('assignments/{assignment}/deactivate', [ClientDspAssignmentController::class, 'deactivate'])->name('assignments.deactivate');

    Route::resource('scheduled-visits', ScheduledVisitController::class)->except(['destroy']);
    Route::post('scheduled-visits/{scheduled_visit}/clock-in', [VisitClockInController::class, 'store'])
        ->name('scheduled-visits.clock-in');
    Route::get('visits/{visit}', [VisitController::class, 'show'])->name('visits.show');
    Route::patch('visits/{visit}/notes', [VisitClockOutController::class, 'updateNotes'])->name('visits.notes');
    Route::post('visits/{visit}/clock-out', [VisitClockOutController::class, 'store'])->name('visits.clock-out');
    Route::post('visits/{visit}/tasks/{visit_task}/complete', [VisitTaskController::class, 'complete'])
        ->scopeBindings()
        ->name('visits.tasks.complete');
    Route::post('visits/{visit}/tasks/{visit_task}/skip', [VisitTaskController::class, 'skip'])
        ->scopeBindings()
        ->name('visits.tasks.skip');

    Route::get('operations', SupervisorOperationsController::class)->name('operations.index');
    Route::get('visit-exceptions', [VisitExceptionController::class, 'index'])->name('visit-exceptions.index');
    Route::get('visit-exceptions/{visit_exception}', [VisitExceptionController::class, 'show'])->name('visit-exceptions.show');
    Route::patch('visit-exceptions/{visit_exception}/review', [VisitExceptionController::class, 'review'])->name('visit-exceptions.review');
    Route::patch('visit-exceptions/{visit_exception}/resolve', [VisitExceptionController::class, 'resolve'])->name('visit-exceptions.resolve');

    Route::get('supervisors', [SupervisorController::class, 'index'])->name('supervisors.index');
    Route::get('supervisors/{employee}', [SupervisorController::class, 'show'])->name('supervisors.show');
    Route::get('attendance', ComingSoonController::class)->name('attendance.index');
    Route::get('compliance', ComingSoonController::class)->name('compliance.index');
    Route::get('reports', ComingSoonController::class)->name('reports.index');
    Route::get('messages', ComingSoonController::class)->name('messages.index');
});

require __DIR__.'/settings.php';
