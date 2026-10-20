export type AttendancePerson = {
    id: number;
    name: string;
};

export type AttendanceTaskCorrectionRow = {
    visit_task_id: number;
    original_status?: string;
    requested_status: string;
    original_skip_reason_id?: number | null;
    requested_skip_reason_id?: number | null;
    original_skip_comment?: string | null;
    requested_skip_comment?: string | null;
    original_completion_note?: string | null;
    requested_completion_note?: string | null;
};

export type AttendanceVisitTask = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    skip_reason_id: number | null;
    skip_comment: string | null;
    completion_note: string | null;
    can_skip: boolean;
};

export type AttendanceSkipReason = {
    id: number;
    name: string;
    code: string;
    requires_comment: boolean;
    requires_explanation: boolean;
};

export type AttendanceCorrectionRecord = {
    id: number;
    scheduled_visit_id: number;
    visit_id: number | null;
    status: string;
    status_label: string;
    is_evv_reconstruction?: boolean;
    original_attendance_status?: string | null;
    overlap_conflict?: boolean;
    proposed_fields?: string[];
    task_corrections?: AttendanceTaskCorrectionRow[];
    can_review?: boolean;
    reason: string;
    note: string | null;
    review_note: string | null;
    requested_by_name: string | null;
    reviewed_by_name: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    original_clock_in: string | null;
    original_clock_out: string | null;
    requested_clock_in: string | null;
    requested_clock_out: string | null;
    original_visit_notes?: string | null;
    requested_visit_notes?: string | null;
    original_handover_note?: string | null;
    requested_handover_note?: string | null;
    employee_name?: string | null;
    client_name?: string | null;
};

export type AttendanceStatusSummary = {
    completed: number;
    in_progress: number;
    late: number;
    missed: number;
    manually_adjusted: number;
    scheduled: number;
    exception: number;
    total: number;
};

export type AttendanceRecord = {
    id: number;
    visit_id: number | null;
    service_date: string;
    service_type: string;
    employee: AttendancePerson;
    client: AttendancePerson;
    supervisor_name: string | null;
    scheduled_time: string;
    original_clock_in: string | null;
    original_clock_out: string | null;
    effective_clock_in: string | null;
    effective_clock_out: string | null;
    worked_duration: string | null;
    original_duration: string | null;
    status: string;
    status_label: string;
    has_gps_issue: boolean;
    gps_label: string | null;
    has_exception: boolean;
    has_adjustment: boolean;
    adjustment_label: string | null;
    is_adjusted: boolean;
    evv_reconstructed?: boolean;
    visit_notes?: string | null;
    handover_note?: string | null;
    can_request_correction: boolean;
    can_reconstruct_missed?: boolean;
    can_reclassify_missed_cancelled?: boolean;
    payroll_locked?: boolean;
    payroll_lock_label?: string | null;
    missed_to_cancelled?: {
        id: number;
        reason: string;
        evidence_note: string | null;
        reclassified_by_name: string | null;
        reclassified_at: string | null;
    } | null;
    pending_correction_id: number | null;
    pending_overlap_conflict?: boolean;
    corrections?: AttendanceCorrectionRecord[];
    visit_segments?: Array<{
        id: number;
        employee_name?: string | null;
        status: string;
        evv_reconstructed?: boolean;
    }>;
    visit_tasks?: AttendanceVisitTask[];
    skip_reasons?: AttendanceSkipReason[];
    clock_in_input?: string | null;
    clock_out_input?: string | null;
};

export type ComplianceSummary = {
    valid: number;
    expiring_soon: number;
    expired: number;
    missing: number;
};

export type ComplianceEmployeeAttention = {
    id: number;
    name: string;
    employee_number: string;
    expired: number;
    expiring_soon: number;
    other: number;
};

export type ComplianceItem = {
    id: string;
    kind: 'credential' | 'training';
    record_id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    title: string;
    type: string;
    expires_on: string | null;
    status: string;
    status_label: string;
    stored_status: string;
};
