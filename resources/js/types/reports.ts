export type ReportSummary = {
    key: string;
    title: string;
    description: string;
};

export type ReportColumn = {
    key: string;
    label: string;
};

export type ReportRow = Record<string, string | number | null>;

export type ReportFilters = {
    from: string;
    to: string;
    employee_id: string;
    client_id: string;
    supervisor_id: string;
    status: string;
};

export type ReportFilterVisibility = {
    dates: boolean;
    employee: boolean;
    client: boolean;
    supervisor: boolean;
    status: boolean;
};

export type ReportTotals = {
    completed_visit_count: number;
    worked_hours: string;
    employee_count: number;
};

export type PayrollFinalizationAudit = {
    id: number;
    action: string;
    action_label: string;
    actor_name: string | null;
    reason: string | null;
    created_at: string | null;
};

export type PayrollFinalizationRecord = {
    id: number;
    period_start: string;
    period_end: string;
    scope: string;
    scope_label: string;
    status: string;
    status_label: string;
    export_ready: boolean;
    finalized_at: string | null;
    finalized_by_name: string | null;
    finalize_note: string | null;
    reopened_at: string | null;
    reopened_by_name: string | null;
    reopen_reason: string | null;
    audits: PayrollFinalizationAudit[];
};

export type PayrollFinalizationState = {
    available: boolean;
    message: string | null;
    scope?: string;
    scope_label?: string;
    period_start?: string;
    period_end?: string;
    employee_id?: number | null;
    record: PayrollFinalizationRecord | null;
    export_ready?: boolean;
    can: {
        finalize: boolean;
        reopen: boolean;
        re_finalize: boolean;
    };
};
