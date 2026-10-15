export type ActiveVisitTask = {
    id: number;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_label: string;
    preferred_timing_label?: string | null;
    is_required: boolean;
    note_required?: boolean;
    can_skip?: boolean;
    is_critical?: boolean;
    status: string;
    status_label: string;
    completed_at: string | null;
    completed_at_label: string | null;
    skipped_at: string | null;
    skip_reason_id: number | null;
    skip_reason_name: string | null;
    skip_comment: string | null;
    completion_note: string | null;
};

export type SkipReasonOption = {
    id: number;
    name: string;
    code: string;
    requires_comment: boolean;
    requires_explanation: boolean;
};

export type VisitSafetyCheckRecord = {
    id: number;
    sequence: number;
    due_at: string;
    due_at_label: string;
    grace_ends_at: string;
    status: string;
    status_label: string;
    completed_at: string | null;
    completed_at_label: string | null;
    outcome_reason: string | null;
    is_documented: boolean;
    requires_reason: boolean;
};

export type ActiveVisitRecord = {
    id: number;
    status: string;
    status_label: string;
    service_type: string;
    clocked_in_at: string;
    clocked_in_at_label: string;
    clocked_out_at: string | null;
    clocked_out_at_label: string | null;
    duration_label?: string | null;
    location_method: string;
    location_status: string;
    location_status_label: string;
    unavailable_reason: string | null;
    latitude: string | null;
    longitude: string | null;
    accuracy: string | null;
    low_location_accuracy?: boolean;
    clock_out_location_method: string | null;
    clock_out_location_status: string | null;
    clock_out_location_status_label: string | null;
    clock_out_unavailable_reason: string | null;
    visit_notes: string | null;
    handover_note: string | null;
    extended_visit_reason?: string | null;
    unfinished_required_acknowledged: boolean;
    safety_check_interval_minutes?: number | null;
    supervision_instruction?: string | null;
    general_safety_confirmed_at?: string | null;
    general_safety_confirmed?: boolean;
    requires_general_safety_confirmation?: boolean;
    past_scheduled_end?: boolean;
    minutes_past_scheduled_end?: number | null;
    requires_extended_visit_reason?: boolean;
    safety_checks?: VisitSafetyCheckRecord[];
    task_summary: {
        total: number;
        completed: number;
        skipped: number;
        pending: number;
        pending_required: number;
    };
    exceptions: Array<{
        id: number;
        type: string;
        type_label: string;
        status: string;
        status_label: string;
        message: string;
        task_title: string | null;
        visit_task_id?: number | null;
        review_notes?: string | null;
        resolution_notes?: string | null;
        reviewed_by_name?: string | null;
        resolved_by_name?: string | null;
        reviewed_at_label?: string | null;
        resolved_at_label?: string | null;
        status_history?: Array<{
            status: string;
            at: string;
            user_id: number;
            user_name: string;
            notes: string | null;
        }>;
        is_high_priority?: boolean;
        is_high_priority_open?: boolean;
    }>;
    has_high_priority_open?: boolean;
    timeline?: Array<{
        id: string;
        title: string;
        detail?: string | null;
        at?: string | null;
        at_label?: string | null;
    }>;
    supervisor?: {
        id: number;
        name: string;
    } | null;
    client: {
        id: number;
        name: string;
        client_number: string;
    };
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    scheduled_visit: {
        id: number;
        service_date: string | null;
        time_label: string;
        shift_name: string | null;
        status: string;
        starts_at_iso?: string | null;
    };
    tasks: ActiveVisitTask[];
};
