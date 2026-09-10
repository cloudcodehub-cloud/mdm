import type { AppRole } from '@/types/auth';

export type DashboardMetric = {
    key: string;
    label: string;
    value: number;
    hint: string;
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
};

export type DashboardActivityItem = {
    id: string;
    title: string;
    detail: string;
    occurred_on: string;
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
};
