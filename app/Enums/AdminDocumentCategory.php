<?php

namespace App\Enums;

enum AdminDocumentCategory: string
{
    case EmployeeCredential = 'employee_credential';
    case EmployeeTraining = 'employee_training';
    case IncidentSubmittedPdf = 'incident_submitted_pdf';
    case IncidentFinalPdf = 'incident_final_pdf';
    case CompletedVisitPdf = 'completed_visit_pdf';
    case HoursAttendanceReport = 'hours_attendance_report';

    public function label(): string
    {
        return match ($this) {
            self::EmployeeCredential => 'Employee credential',
            self::EmployeeTraining => 'Employee training evidence',
            self::IncidentSubmittedPdf => 'Incident submitted PDF',
            self::IncidentFinalPdf => 'Incident final PDF',
            self::CompletedVisitPdf => 'Completed Visit PDF',
            self::HoursAttendanceReport => 'Hours & Attendance report',
        };
    }

    public function sourceArea(): string
    {
        return match ($this) {
            self::EmployeeCredential, self::EmployeeTraining => 'hr_compliance',
            self::IncidentSubmittedPdf, self::IncidentFinalPdf => 'incidents',
            self::CompletedVisitPdf => 'visits',
            self::HoursAttendanceReport => 'attendance_hours',
        };
    }

    public function sourceAreaLabel(): string
    {
        return match ($this->sourceArea()) {
            'hr_compliance' => 'HR / Compliance',
            'incidents' => 'Incidents',
            'visits' => 'Visits',
            'attendance_hours' => 'Attendance / Hours',
            default => $this->sourceArea(),
        };
    }

    public function origin(): AdminDocumentOrigin
    {
        return match ($this) {
            self::EmployeeCredential, self::EmployeeTraining => AdminDocumentOrigin::Uploaded,
            default => AdminDocumentOrigin::Generated,
        };
    }
}
