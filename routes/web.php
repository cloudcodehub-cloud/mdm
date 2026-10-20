<?php

use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AvailabilityRequestController;
use App\Http\Controllers\CarePlanController;
use App\Http\Controllers\CareServiceController;
use App\Http\Controllers\ClientAuthorizationController;
use App\Http\Controllers\ClientCareSetupController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDspAssignmentController;
use App\Http\Controllers\ClientServiceLocationController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ComplianceEvidenceController;
use App\Http\Controllers\ComplianceRequirementController;
use App\Http\Controllers\ComplianceTemporaryExceptionController;
use App\Http\Controllers\ConversationAttachmentController;
use App\Http\Controllers\CriticalAlertController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DspAvailabilityController;
use App\Http\Controllers\DspComplianceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeProfileChangeController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\ItTechAccessController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissedVisitReclassificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationLogoController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduledVisitController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SupervisorOperationsController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TemporarySupervisorCoverageController;
use App\Http\Controllers\VisitAdministrativeCloseController;
use App\Http\Controllers\VisitClockInController;
use App\Http\Controllers\VisitClockOutController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitExceptionController;
use App\Http\Controllers\VisitHandoffController;
use App\Http\Controllers\VisitSafetyCheckController;
use App\Http\Controllers\VisitSafetyInstructionController;
use App\Http\Controllers\VisitTaskController;
use Illuminate\Auth\Middleware\RequirePassword;
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
    Route::get('organization/logo', OrganizationLogoController::class)->name('organization.logo');

    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');
    Route::get('employees/{employee}/lifecycle-preview', [EmployeeController::class, 'lifecyclePreview'])
        ->name('employees.lifecycle.preview');
    Route::get('employees/{employee}/photo', [ProfilePhotoController::class, 'employee'])->name('employees.photo');
    Route::delete('employees/{employee}/photo', [ProfilePhotoController::class, 'destroyEmployee'])->name('employees.photo.destroy');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::get('clients/{client}/photo', [ProfilePhotoController::class, 'client'])->name('clients.photo');
    Route::delete('clients/{client}/photo', [ProfilePhotoController::class, 'destroyClient'])->name('clients.photo.destroy');
    Route::get('clients/{client}/setup', [ClientCareSetupController::class, 'edit'])->name('clients.setup.edit');
    Route::post('clients/{client}/setup', [ClientCareSetupController::class, 'update'])->name('clients.setup.update');
    Route::post('clients/{client}/care-plans', [CarePlanController::class, 'store'])->name('clients.care-plans.store');
    Route::patch('care-plans/{care_plan}', [CarePlanController::class, 'update'])->name('care-plans.update');
    Route::put('care-plans/{care_plan}/tasks', [CarePlanController::class, 'syncTasks'])->name('care-plans.tasks.sync');
    Route::post('care-plans/{care_plan}/cancel-pending', [CarePlanController::class, 'cancelPending'])->name('care-plans.cancel-pending');
    Route::post('care-plans/{care_plan}/correct-effective-date', [CarePlanController::class, 'correctEffectiveDate'])
        ->name('care-plans.correct-effective-date');
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::get('clients/{client}/lifecycle-preview', [ClientController::class, 'lifecyclePreview'])
        ->name('clients.lifecycle.preview');
    Route::post('clients/{client}/assignments', [ClientDspAssignmentController::class, 'store'])->name('clients.assignments.store');
    Route::patch('assignments/{assignment}/deactivate', [ClientDspAssignmentController::class, 'deactivate'])->name('assignments.deactivate');
    Route::post('clients/{client}/critical-alerts', [CriticalAlertController::class, 'store'])
        ->name('clients.critical-alerts.store');
    Route::patch('clients/{client}/critical-alerts/{critical_alert}', [CriticalAlertController::class, 'update'])
        ->name('clients.critical-alerts.update');
    Route::post('clients/{client}/critical-alerts/{critical_alert}/deactivate', [CriticalAlertController::class, 'deactivate'])
        ->name('clients.critical-alerts.deactivate');
    Route::post('clients/{client}/contacts', [ClientContactController::class, 'store'])
        ->name('clients.contacts.store');
    Route::patch('clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])
        ->name('clients.contacts.update');
    Route::delete('clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])
        ->name('clients.contacts.destroy');
    Route::post('clients/{client}/service-locations', [ClientServiceLocationController::class, 'store'])
        ->name('clients.service-locations.store');
    Route::patch('clients/{client}/service-locations/{service_location}', [ClientServiceLocationController::class, 'update'])
        ->name('clients.service-locations.update');
    Route::post('clients/{client}/service-locations/{service_location}/deactivate', [ClientServiceLocationController::class, 'deactivate'])
        ->name('clients.service-locations.deactivate');
    Route::post('clients/{client}/authorizations', [ClientAuthorizationController::class, 'store'])
        ->name('clients.authorizations.store');
    Route::patch('clients/{client}/authorizations/{authorization}', [ClientAuthorizationController::class, 'update'])
        ->name('clients.authorizations.update');

    Route::get('care-services', [CareServiceController::class, 'index'])->name('care-services.index');
    Route::post('care-services', [CareServiceController::class, 'store'])->name('care-services.store');
    Route::patch('care-services/{care_service}', [CareServiceController::class, 'update'])->name('care-services.update');

    Route::get('scheduled-visits/care-preview', [ScheduledVisitController::class, 'carePreview'])
        ->name('scheduled-visits.care-preview');
    Route::get('scheduled-visits/availability-board', [ScheduledVisitController::class, 'availabilityBoard'])
        ->name('scheduled-visits.availability-board');
    Route::get('scheduled-visits/calendar', [ScheduledVisitController::class, 'calendar'])
        ->name('scheduled-visits.calendar');
    Route::resource('scheduled-visits', ScheduledVisitController::class)->except(['destroy']);
    Route::post('scheduled-visits/{scheduled_visit}/duplicate', [ScheduledVisitController::class, 'duplicate'])
        ->name('scheduled-visits.duplicate');
    Route::post('scheduled-visits/{scheduled_visit}/replace', [ScheduledVisitController::class, 'replace'])
        ->name('scheduled-visits.replace');
    Route::post('scheduled-visits/{scheduled_visit}/availability-override/acknowledge', [ScheduledVisitController::class, 'acknowledgeAvailabilityOverride'])
        ->name('scheduled-visits.availability-override.acknowledge');

    Route::get('my-availability', [DspAvailabilityController::class, 'index'])->name('my-availability.index');
    Route::post('my-availability/requests', [DspAvailabilityController::class, 'store'])->name('my-availability.store');
    Route::post('my-availability/time-off', [DspAvailabilityController::class, 'storeTimeOff'])->name('my-availability.time-off');
    Route::post('availability/override-weekly', [DspAvailabilityController::class, 'overrideWeekly'])->name('availability.override-weekly');
    Route::get('availability-requests', [AvailabilityRequestController::class, 'index'])->name('availability-requests.index');
    Route::patch('availability-requests/{availability_request}/approve', [AvailabilityRequestController::class, 'approve'])
        ->name('availability-requests.approve');
    Route::patch('availability-requests/{availability_request}/reject', [AvailabilityRequestController::class, 'reject'])
        ->name('availability-requests.reject');
    Route::patch('time-off/{time_off}/approve', [AvailabilityRequestController::class, 'approveTimeOff'])
        ->name('time-off.approve');
    Route::patch('time-off/{time_off}/reject', [AvailabilityRequestController::class, 'rejectTimeOff'])
        ->name('time-off.reject');
    Route::post('scheduled-visits/{scheduled_visit}/clock-in', [VisitClockInController::class, 'store'])
        ->name('scheduled-visits.clock-in');
    Route::get('scheduled-visits/{scheduled_visit}/documents/visit-handout', [DocumentController::class, 'visitHandoutPreview'])
        ->name('documents.visit-handout.preview');
    Route::get('scheduled-visits/{scheduled_visit}/documents/visit-handout/download', [DocumentController::class, 'visitHandoutPdf'])
        ->name('documents.visit-handout.pdf');
    Route::get('visits/{visit}', [VisitController::class, 'show'])->name('visits.show');
    Route::get('visits/{visit}/documents/completed-visit', [DocumentController::class, 'completedVisitPreview'])
        ->name('documents.completed-visit.preview');
    Route::get('visits/{visit}/documents/completed-visit/download', [DocumentController::class, 'completedVisitPdf'])
        ->name('documents.completed-visit.pdf');
    Route::get('incidents/{incident}/documents/submitted', [DocumentController::class, 'incidentSubmittedPreview'])
        ->name('documents.incident-submitted.preview');
    Route::get('incidents/{incident}/documents/submitted/download', [DocumentController::class, 'incidentSubmittedPdf'])
        ->name('documents.incident-submitted.pdf');
    Route::get('incidents/{incident}/documents/final', [DocumentController::class, 'incidentFinalPreview'])
        ->name('documents.incident-final.preview');
    Route::get('incidents/{incident}/documents/final/download', [DocumentController::class, 'incidentFinalPdf'])
        ->name('documents.incident-final.pdf');
    Route::get('reports/hours-attendance/documents', [DocumentController::class, 'hoursAttendancePreview'])
        ->name('documents.hours-attendance.preview');
    Route::get('reports/hours-attendance/documents/download', [DocumentController::class, 'hoursAttendancePdf'])
        ->name('documents.hours-attendance.pdf');
    Route::patch('visits/{visit}/notes', [VisitClockOutController::class, 'updateNotes'])->name('visits.notes');
    Route::post('visits/{visit}/clock-out', [VisitClockOutController::class, 'store'])->name('visits.clock-out');
    Route::post('visits/{visit}/administrative-close', [VisitAdministrativeCloseController::class, 'store'])
        ->name('visits.administrative-close');
    Route::post('visits/{visit}/handoff', [VisitHandoffController::class, 'store'])
        ->name('visits.handoff');
    Route::post('visits/{visit}/safety-confirmation', [VisitSafetyCheckController::class, 'confirmGeneral'])
        ->name('visits.safety-confirmation');
    Route::post('visits/{visit}/safety-checks/{safety_check}/acknowledge', [VisitSafetyCheckController::class, 'acknowledge'])
        ->name('visits.safety-checks.acknowledge');
    Route::post('visits/{visit}/critical-alerts/{critical_alert}/acknowledge', [CriticalAlertController::class, 'acknowledge'])
        ->name('visits.critical-alerts.acknowledge');
    Route::post('visits/{visit}/safety-instructions', [VisitSafetyInstructionController::class, 'store'])
        ->name('visits.safety-instructions.store');
    Route::post('visits/{visit}/safety-instructions/{safety_instruction}/acknowledge', [VisitSafetyInstructionController::class, 'acknowledge'])
        ->name('visits.safety-instructions.acknowledge');
    Route::post('visits/{visit}/tasks/{visit_task}/complete', [VisitTaskController::class, 'complete'])
        ->scopeBindings()
        ->name('visits.tasks.complete');
    Route::post('visits/{visit}/tasks/{visit_task}/skip', [VisitTaskController::class, 'skip'])
        ->scopeBindings()
        ->name('visits.tasks.skip');

    Route::get('operations', SupervisorOperationsController::class)->name('operations.index');
    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('visits/{visit}/incidents', [IncidentController::class, 'store'])->name('visits.incidents.store');
    Route::patch('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('incidents/{incident}/discard', [IncidentController::class, 'discard'])->name('incidents.discard');
    Route::post('incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge'])
        ->name('incidents.acknowledge');
    Route::patch('incidents/{incident}/classification', [IncidentController::class, 'updateClassification'])
        ->name('incidents.classification.update');
    Route::post('incidents/{incident}/reviews', [IncidentController::class, 'storeReview'])
        ->name('incidents.reviews.store');
    Route::patch('incidents/{incident}/investigation', [IncidentController::class, 'updateInvestigation'])
        ->name('incidents.investigation.update');
    Route::post('incidents/{incident}/follow-ups', [IncidentController::class, 'storeFollowUp'])
        ->name('incidents.follow-ups.store');
    Route::post('incidents/follow-ups/{incident_follow_up}/complete', [IncidentController::class, 'completeFollowUp'])
        ->name('incidents.follow-ups.complete');
    Route::patch('incidents/follow-ups/{incident_follow_up}/reassign', [IncidentController::class, 'reassignFollowUp'])
        ->name('incidents.follow-ups.reassign');
    Route::post('incidents/{incident}/reporting-records', [IncidentController::class, 'storeReportingRecord'])
        ->name('incidents.reporting-records.store');
    Route::post('incidents/{incident}/amendments', [IncidentController::class, 'storeAmendment'])
        ->name('incidents.amendments.store');
    Route::post('incidents/{incident}/close', [IncidentController::class, 'close'])->name('incidents.close');
    Route::post('incidents/{incident}/reopen', [IncidentController::class, 'reopen'])->name('incidents.reopen');
    Route::get('visit-exceptions', [VisitExceptionController::class, 'index'])->name('visit-exceptions.index');
    Route::get('visit-exceptions/{visit_exception}', [VisitExceptionController::class, 'show'])->name('visit-exceptions.show');
    Route::patch('visit-exceptions/{visit_exception}/review', [VisitExceptionController::class, 'review'])->name('visit-exceptions.review');
    Route::patch('visit-exceptions/{visit_exception}/resolve', [VisitExceptionController::class, 'resolve'])->name('visit-exceptions.resolve');
    Route::patch('visit-exceptions/{visit_exception}/follow-up', [VisitExceptionController::class, 'followUp'])->name('visit-exceptions.follow-up');
    Route::post('visit-exceptions/{visit_exception}/reopen', [VisitExceptionController::class, 'reopen'])->name('visit-exceptions.reopen');

    Route::get('supervisors', [SupervisorController::class, 'index'])->name('supervisors.index');
    Route::get('supervisors/create', [SupervisorController::class, 'create'])->name('supervisors.create');
    Route::post('supervisors', [SupervisorController::class, 'store'])->name('supervisors.store');
    Route::get('supervisors/{employee}', [SupervisorController::class, 'show'])->name('supervisors.show');
    Route::post('supervisors/{employee}/team', [SupervisorController::class, 'reassignTeam'])->name('supervisors.team');
    Route::post('supervisors/{employee}/caseload', [SupervisorController::class, 'reassignCaseload'])->name('supervisors.caseload');
    Route::post('supervisors/{employee}/revoke', [SupervisorController::class, 'revoke'])->name('supervisors.revoke');
    Route::post('supervisors/{employee}/temporary-coverages', [TemporarySupervisorCoverageController::class, 'store'])
        ->name('supervisors.temporary-coverages.store');
    Route::patch('temporary-supervisor-coverages/{temporary_supervisor_coverage}', [TemporarySupervisorCoverageController::class, 'update'])
        ->name('temporary-supervisor-coverages.update');
    Route::post('temporary-supervisor-coverages/{temporary_supervisor_coverage}/revoke', [TemporarySupervisorCoverageController::class, 'revoke'])
        ->name('temporary-supervisor-coverages.revoke');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/{scheduled_visit}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('attendance/{scheduled_visit}/corrections', [AttendanceCorrectionController::class, 'store'])
        ->name('attendance.corrections.store');
    Route::patch('attendance/corrections/{attendance_correction}/approve', [AttendanceCorrectionController::class, 'approve'])
        ->name('attendance.corrections.approve');
    Route::patch('attendance/corrections/{attendance_correction}/reject', [AttendanceCorrectionController::class, 'reject'])
        ->name('attendance.corrections.reject');
    Route::post('attendance/{scheduled_visit}/reclassify-missed-cancelled', [MissedVisitReclassificationController::class, 'store'])
        ->name('attendance.reclassify-missed-cancelled');
    Route::post('payroll-periods/finalize', [PayrollPeriodController::class, 'finalize'])
        ->middleware(RequirePassword::class)
        ->name('payroll-periods.finalize');
    Route::post('payroll-periods/{payroll_period}/reopen', [PayrollPeriodController::class, 'reopen'])
        ->middleware(RequirePassword::class)
        ->name('payroll-periods.reopen');
    Route::post('payroll-periods/{payroll_period}/re-finalize', [PayrollPeriodController::class, 'reFinalize'])
        ->middleware(RequirePassword::class)
        ->name('payroll-periods.re-finalize');
    Route::get('compliance', ComplianceController::class)->name('compliance.index');
    Route::get('compliance/requirements', [ComplianceRequirementController::class, 'index'])
        ->name('compliance.requirements.index');
    Route::post('compliance/requirements', [ComplianceRequirementController::class, 'store'])
        ->name('compliance.requirements.store');
    Route::put('compliance/requirements/{requirement}', [ComplianceRequirementController::class, 'update'])
        ->name('compliance.requirements.update');
    Route::post('compliance/requirements/{requirement}/activate', [ComplianceRequirementController::class, 'activate'])
        ->name('compliance.requirements.activate');
    Route::post('compliance/requirements/{requirement}/deactivate', [ComplianceRequirementController::class, 'deactivate'])
        ->name('compliance.requirements.deactivate');
    Route::get('my-compliance', DspComplianceController::class)->name('my-compliance.index');
    Route::get('my-profile-changes', [EmployeeProfileChangeController::class, 'mine'])
        ->name('my-profile-changes.index');
    Route::post('my-profile-changes', [EmployeeProfileChangeController::class, 'store'])
        ->name('my-profile-changes.store');
    Route::get('profile-change-requests', [EmployeeProfileChangeController::class, 'index'])
        ->name('profile-change-requests.index');
    Route::patch('profile-change-requests/{profile_change}/approve', [EmployeeProfileChangeController::class, 'approve'])
        ->name('profile-change-requests.approve');
    Route::patch('profile-change-requests/{profile_change}/reject', [EmployeeProfileChangeController::class, 'reject'])
        ->name('profile-change-requests.reject');
    Route::post('employees/{employee}/compliance-evidence', [ComplianceEvidenceController::class, 'store'])
        ->name('employees.compliance-evidence.store');
    Route::post('compliance-credentials/{credential}/review', [ComplianceEvidenceController::class, 'reviewCredential'])
        ->name('compliance-credentials.review');
    Route::post('compliance-trainings/{training}/review', [ComplianceEvidenceController::class, 'reviewTraining'])
        ->name('compliance-trainings.review');
    Route::post('employees/{employee}/compliance-exceptions', [ComplianceTemporaryExceptionController::class, 'store'])
        ->name('employees.compliance-exceptions.store');
    Route::post('compliance-exceptions/{exception}/revoke', [ComplianceTemporaryExceptionController::class, 'revoke'])
        ->name('compliance-exceptions.revoke');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

    Route::get('admin/documents', [AdminDocumentController::class, 'index'])->name('admin.documents.index');
    Route::get('admin/documents/credentials/{credential}/download', [AdminDocumentController::class, 'downloadCredential'])
        ->name('admin.documents.credentials.download');
    Route::get('admin/documents/trainings/{training}/download', [AdminDocumentController::class, 'downloadTraining'])
        ->name('admin.documents.trainings.download');
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('messages/conversations', [MessageController::class, 'store'])->name('conversations.store');
    Route::post('messages/groups', [MessageController::class, 'storeGroup'])->name('conversations.groups.store');
    Route::get('messages/with/{user}', [MessageController::class, 'preview'])->name('messages.preview');
    Route::get('messages/admin-review', [MessageController::class, 'reviewIndex'])->name('messages.admin-review');
    Route::post('messages/{conversation}/admin-review', [MessageController::class, 'review'])
        ->name('messages.admin-review.store');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}/messages', [MessageController::class, 'storeMessage'])
        ->name('conversations.messages.store');
    Route::patch('messages/{conversation}/name', [MessageController::class, 'renameGroup'])
        ->name('conversations.rename');
    Route::post('messages/{conversation}/participants', [MessageController::class, 'addParticipants'])
        ->name('conversations.participants.store');
    Route::delete('messages/{conversation}/participants/{user}', [MessageController::class, 'removeParticipant'])
        ->name('conversations.participants.destroy');
    Route::post('messages/{conversation}/leave', [MessageController::class, 'leave'])
        ->name('conversations.leave');
    Route::get('conversation-attachments/{conversation_message_attachment}', [ConversationAttachmentController::class, 'show'])
        ->name('conversation-attachments.show');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])
        ->name('announcements.read');

    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('support-tickets/create', [SupportTicketController::class, 'create'])->name('support-tickets.create');
    Route::post('support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::get('support-tickets/{support_ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{support_ticket}/claim', [SupportTicketController::class, 'claim'])
        ->name('support-tickets.claim');
    Route::post('support-tickets/{support_ticket}/reply', [SupportTicketController::class, 'reply'])
        ->name('support-tickets.reply');
    Route::post('support-tickets/{support_ticket}/resolve', [SupportTicketController::class, 'resolve'])
        ->name('support-tickets.resolve');
    Route::post('support-tickets/{support_ticket}/reopen', [SupportTicketController::class, 'reopen'])
        ->name('support-tickets.reopen');
    Route::post('support-tickets/{support_ticket}/cancel', [SupportTicketController::class, 'cancel'])
        ->name('support-tickets.cancel');
    Route::post('support-tickets/{support_ticket}/reassign', [SupportTicketController::class, 'reassign'])
        ->name('support-tickets.reassign');
    Route::post('support-tickets/{support_ticket}/takeover', [SupportTicketController::class, 'takeover'])
        ->name('support-tickets.takeover');
    Route::post('support-tickets/{support_ticket}/duplicate', [SupportTicketController::class, 'markDuplicate'])
        ->name('support-tickets.duplicate');
    Route::get('support-ticket-attachments/{support_ticket_attachment}', [SupportTicketAttachmentController::class, 'show'])
        ->name('support-ticket-attachments.show');
    Route::patch('employees/{employee}/it-tech-access', [ItTechAccessController::class, 'update'])
        ->name('employees.it-tech-access');

    Route::get('inbox/activity', [NotificationController::class, 'activity'])->name('inbox.activity');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
