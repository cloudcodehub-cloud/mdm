export type TimelineSegment = {
    start: number;
    end: number;
    state: string;
    label: string | null;
};

export type AvailabilityDspRow = {
    id: number;
    name: string;
    employee_number: string;
    assigned_to_client: boolean;
    fully_available: boolean;
    hard_blocked: boolean;
    block_reason: string | null;
    score: number;
    reasons: string[];
    reason_label: string;
    warnings: string[];
    workload: {
        day_hours: number;
        week_hours: number;
        visit_count: number;
    };
    capacity_percent: number;
    preferred_daypart: string | null;
    partial: { start: number; end: number } | null;
    timeline: TimelineSegment[];
    history_count: number;
};

export type AvailabilityBoard = {
    supervisor: { id: number; name: string; source: string } | null;
    requested: { start: number; end: number; label: string } | null;
    dsps: AvailabilityDspRow[];
    coverage: {
        message: string;
        options: Array<{ employee_id: number; name: string; label: string }>;
    } | null;
    authorization: { level: string; message: string } | null;
};
