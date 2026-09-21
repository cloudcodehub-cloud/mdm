export type OperationsPerson = {
    id: number;
    name: string;
    employee_number?: string;
    job_title?: string | null;
    client_number?: string;
};

export type OperationsTaskSummary = {
    total: number;
    completed: number;
    skipped: number;
    pending: number;
};

export type OperationsVisitRow = {
    scheduled_visit_id: number;
    visit_id: number | null;
    service_type: string;
    service_date: string;
    time_label: string;
    scheduled_status: string;
    visit_status: string | null;
    operational_status: string;
    operational_status_label: string;
    clocked_in_at_label: string | null;
    clocked_out_at_label: string | null;
    location_status: string | null;
    location_status_label: string | null;
    handover_note: string | null;
    visit_notes: string | null;
    open_exception_count: number;
    has_high_priority_open?: boolean;
    task_summary: OperationsTaskSummary;
    client: { id: number; name: string; client_number: string };
    employee: { id: number; name: string; employee_number: string };
};

export type VisitExceptionRecord = {
    id: number;
    visit_id: number;
    visit_task_id: number | null;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    message: string;
    context: Record<string, unknown> | null;
    task_title: string | null;
    review_notes: string | null;
    resolution_notes: string | null;
    reviewed_at_label: string | null;
    resolved_at_label: string | null;
    reviewed_by_name: string | null;
    resolved_by_name: string | null;
    status_history: Array<{
        status: string;
        at: string;
        user_id: number;
        user_name: string;
        notes: string | null;
    }>;
    created_at_label: string | null;
    client_name: string | null;
    dsp_name: string | null;
    service_type: string | null;
    priority: 'high' | 'standard';
    is_high_priority: boolean;
    is_high_priority_open?: boolean;
};

export type OperationsBoard = {
    today: string;
    timezone: string;
    timezone_label: string;
    metrics: Array<{
        key: string;
        label: string;
        value: number;
        hint: string;
        href?: string | null;
    }>;
    assigned_dsps: OperationsPerson[];
    assigned_clients: OperationsPerson[];
    today_visits: OperationsVisitRow[];
    active_visits: OperationsVisitRow[];
    completed_visits: OperationsVisitRow[];
    skipped_tasks: Array<{
        id: number;
        visit_id: number;
        title: string;
        is_required: boolean;
        skip_reason_name: string | null;
        skip_comment: string | null;
        client_name: string;
        dsp_name: string;
    }>;
    handover_notes: Array<{
        id: number;
        visit_id: number;
        handover_note: string | null;
        client_name: string;
        dsp_name: string;
        service_type: string;
    }>;
    exceptions: {
        open: VisitExceptionRecord[];
        high_priority_open: VisitExceptionRecord[];
        gps: VisitExceptionRecord[];
        client_refusals: VisitExceptionRecord[];
        critical_skips: VisitExceptionRecord[];
        unfinished: VisitExceptionRecord[];
    };
};
