export type AttendancePerson = {
    id: number;
    name: string;
};

export type AttendanceCorrectionRecord = {
    id: number;
    scheduled_visit_id: number;
    visit_id: number | null;
    status: string;
    status_label: string;
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
    employee_name?: string | null;
    client_name?: string | null;
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
    can_request_correction: boolean;
    pending_correction_id: number | null;
    corrections?: AttendanceCorrectionRecord[];
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
