<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\CarePlanController;
use App\Http\Controllers\CareServiceController;
use App\Http\Controllers\ClientCareSetupController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDspAssignmentController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduledVisitController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SupervisorOperationsController;
use App\Http\Controllers\VisitClockInController;
use App\Http\Controllers\VisitClockOutController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitExceptionController;
use App\Http\Controllers\VisitTaskController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::get('clients/{client}/setup', [ClientCareSetupController::class, 'edit'])->name('clients.setup.edit');
    Route::post('clients/{client}/setup', [ClientCareSetupController::class, 'update'])->name('clients.setup.update');
    Route::post('clients/{client}/care-plans', [CarePlanController::class, 'store'])->name('clients.care-plans.store');
    Route::patch('care-plans/{care_plan}', [CarePlanController::class, 'update'])->name('care-plans.update');
    Route::put('care-plans/{care_plan}/tasks', [CarePlanController::class, 'syncTasks'])->name('care-plans.tasks.sync');
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::post('clients/{client}/assignments', [ClientDspAssignmentController::class, 'store'])->name('clients.assignments.store');
    Route::patch('assignments/{assignment}/deactivate', [ClientDspAssignmentController::class, 'deactivate'])->name('assignments.deactivate');

    Route::get('care-services', [CareServiceController::class, 'index'])->name('care-services.index');
    Route::post('care-services', [CareServiceController::class, 'store'])->name('care-services.store');
    Route::patch('care-services/{care_service}', [CareServiceController::class, 'update'])->name('care-services.update');

    Route::get('scheduled-visits/care-preview', [ScheduledVisitController::class, 'carePreview'])
        ->name('scheduled-visits.care-preview');
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
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/{scheduled_visit}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('attendance/{scheduled_visit}/corrections', [AttendanceCorrectionController::class, 'store'])
        ->name('attendance.corrections.store');
    Route::patch('attendance/corrections/{attendance_correction}/approve', [AttendanceCorrectionController::class, 'approve'])
        ->name('attendance.corrections.approve');
    Route::patch('attendance/corrections/{attendance_correction}/reject', [AttendanceCorrectionController::class, 'reject'])
        ->name('attendance.corrections.reject');
    Route::get('compliance', ComplianceController::class)->name('compliance.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('messages/conversations', [MessageController::class, 'store'])->name('conversations.store');
    Route::get('messages/with/{user}', [MessageController::class, 'preview'])->name('messages.preview');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}/messages', [MessageController::class, 'storeMessage'])
        ->name('conversations.messages.store');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])
        ->name('announcements.read');

    Route::get('inbox/activity', [NotificationController::class, 'activity'])->name('inbox.activity');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
