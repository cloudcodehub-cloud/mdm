import type { AppRole } from '@/types/auth';

export type DashboardMetric = {
    key: string;
    label: string;
    value: number;
    hint: string;
    href?: string | null;
};

export type DashboardClient = {
    id: number;
    name: string;
    client_number: string;
};

export type DashboardEmployee = {
    id: number;
    name: string;
    employee_number?: string;
    job_title?: string | null;
    visits_today?: number;
    in_progress?: number;
    attention?: number;
};

export type DashboardVisit = {
    id: number;
    service_date: string;
    service_type: string;
    status: string;
    time_label: string;
    shift_name: string | null;
    client: DashboardClient;
    employee: DashboardEmployee;
};

export type DashboardAttentionItem = {
    id: string;
    tone: 'danger' | 'warning' | 'neutral';
    title: string;
    detail: string;
    href?: string | null;
};

export type DashboardActivityItem = {
    id: string;
    title: string;
    detail: string;
    occurred_on: string;
};

export type DashboardTaskProgress = {
    completed: number;
    pending: number;
    skipped: number;
    total: number;
    percent: number;
};

export type DashboardActiveVisit = {
    id: number;
    scheduled_visit_id: number;
    service_type: string;
    clocked_in_at: string;
    location_status: string;
    location_status_label: string;
    client: DashboardClient;
    task_progress?: DashboardTaskProgress;
};

export type ClockInVisitSummary = {
    id: number;
    service_date: string | null;
    service_type: string;
    time_label: string;
    client: DashboardClient;
};

export type TodayVisitSummary = {
    scheduled: number;
    in_progress: number;
    completed: number;
    attention: number;
    total: number;
};

export type ComplianceHealth = {
    valid: number;
    expiring_soon: number;
    expired: number;
    missing: number;
    tracked: number;
    valid_percent: number;
};

export type VisitTrendPoint = {
    date: string;
    label: string;
    value: number;
};

export type DashboardPayload = {
    role: AppRole;
    greeting_name: string;
    today: string;
    metrics: DashboardMetric[];
    today_visits: DashboardVisit[];
    upcoming_visits: DashboardVisit[];
    assigned_dsps: DashboardEmployee[];
    assigned_clients: DashboardClient[];
    attention_items: DashboardAttentionItem[];
    activity: DashboardActivityItem[];
    active_visit: DashboardActiveVisit | null;
    clock_in_visit: ClockInVisitSummary | null;
    announcements: import('./messaging').AnnouncementRecord[];
    today_visit_summary: TodayVisitSummary;
    compliance_health: ComplianceHealth | null;
    open_exceptions: number;
    visit_trend: VisitTrendPoint[];
    work_items?: import('./care').DspWorkItem[];
};
