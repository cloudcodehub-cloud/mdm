export type TaskCatalogItem = {
    id: number;
    slug: string;
    category: string;
    category_label: string;
    title: string;
    instructions: string | null;
    default_recurrence: string;
    default_recurrence_label: string;
    default_recurrence_detail: string | null;
    default_weekdays: number[] | null;
    default_interval_weeks: number | null;
    default_preferred_timing: string | null;
    default_preferred_timing_label: string | null;
    default_is_required: boolean;
    default_note_required: boolean;
    default_can_skip: boolean;
    default_is_critical: boolean;
};

export type TaskCatalogBundle = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    item_ids: number[];
};

export type TaskCatalogPayload = {
    categories: Array<{ value: string; label: string }>;
    items: TaskCatalogItem[];
    bundles: TaskCatalogBundle[];
};

export type CarePlanTaskDraft = {
    id?: number;
    catalog_item_id?: number | null;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_detail: string | null;
    weekdays: number[] | null;
    interval_weeks: number | null;
    preferred_timing: string | null;
    is_required: boolean;
    note_required: boolean;
    can_skip: boolean;
    is_critical: boolean;
};

export type CareOverviewItem = {
    id: number;
    title: string;
    instructions: string | null;
    recurrence: string;
    recurrence_label: string;
    preferred_timing_label: string | null;
    is_required: boolean;
    completable: boolean;
    next_on: string;
    next_on_label: string;
    summary: string;
};

export type CareHistoryItem = {
    id: number;
    task_title: string;
    note: string | null;
    dsp_name: string;
    previous_dsp_user_id: number | null;
    previous_dsp_available: boolean;
    visit_id: number;
    occurred_at: string | null;
    occurred_at_label: string | null;
    context_label: string;
};

export type CareOverview = {
    today: CareOverviewItem[];
    upcoming: CareOverviewItem[];
    history: CareHistoryItem[];
    services?: Array<{ id: number; name: string; slug: string }>;
};

export type CareServiceRecord = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    is_active: boolean;
    note_required: boolean;
    supervisor_review_expected: boolean;
    payer_code: string | null;
    sort_order: number;
    recommended_bundle_ids: number[];
    recommended_item_ids: number[];
    recommended_bundle_names: string[];
};

export type CareServiceOption = {
    id: number;
    name: string;
    slug: string;
};

export type SupervisorContact = {
    user_id: number;
    name: string;
    available: boolean;
    role_label?: string;
};

export type DspWorkItem = {
    key: string;
    priority: number;
    kind: string;
    client: { id: number; name: string; client_number: string };
    service_type: string;
    scheduled_time: string | null;
    state: string;
    state_label: string;
    task_progress: {
        completed: number;
        pending: number;
        skipped: number;
        total: number;
        percent: number;
    } | null;
    action_label: string;
    href: string;
    has_exception?: boolean;
};
