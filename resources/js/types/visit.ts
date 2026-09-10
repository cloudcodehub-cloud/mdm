export type ActiveVisitTask = {
    id: number;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_label: string;
    is_required: boolean;
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

export type ActiveVisitRecord = {
    id: number;
    status: string;
    status_label: string;
    service_type: string;
    clocked_in_at: string;
    clocked_in_at_label: string;
    clocked_out_at: string | null;
    clocked_out_at_label: string | null;
    location_method: string;
    location_status: string;
    location_status_label: string;
    unavailable_reason: string | null;
    latitude: string | null;
    longitude: string | null;
    accuracy: string | null;
    clock_out_location_method: string | null;
    clock_out_location_status: string | null;
    clock_out_location_status_label: string | null;
    clock_out_unavailable_reason: string | null;
    visit_notes: string | null;
    handover_note: string | null;
    unfinished_required_acknowledged: boolean;
    task_summary: {
        total: number;
        completed: number;
        skipped: number;
        pending: number;
        pending_required: number;
    };
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
    };
    tasks: ActiveVisitTask[];
};
