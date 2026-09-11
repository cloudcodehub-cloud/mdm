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
