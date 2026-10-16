export type IncidentCategoryOption = {
    value: string;
    label: string;
    subcategories: Array<{ value: string; label: string }>;
};

export type IncidentListItem = {
    id: number;
    tracking_number: string | null;
    status: string;
    status_label: string;
    original_category: string | null;
    original_subcategory: string | null;
    official_category: string | null;
    official_subcategory: string | null;
    summary: string | null;
    occurred_at: string | null;
    occurred_at_label: string | null;
    submitted_at: string | null;
    submitted_at_label: string | null;
    acknowledged_at: string | null;
    acknowledged_at_label: string | null;
    is_acknowledged: boolean;
    requires_acknowledgment: boolean;
    overdue_for_acknowledgment: boolean;
    acknowledgment_condition: string;
    client: {
        id: number;
        name: string | null;
        client_number: string | null;
    };
    reporter: {
        id: number;
        name: string | null;
    };
    responsible_supervisor: {
        id: number;
        name: string | null;
    } | null;
    visit_id: number;
    can_acknowledge: boolean;
};

export type IncidentDetail = IncidentListItem & {
    immediate_action: string | null;
    injured: boolean;
    injury_type: string | null;
    injury_body_location: string | null;
    injury_medical_check: string | null;
    injury_notes: string | null;
    intervention: boolean;
    intervention_type: string | null;
    intervention_started_at: string | null;
    intervention_stopped_at: string | null;
    intervention_notes: string | null;
    supervisor_notified: boolean;
    additional_notes: string | null;
    latitude: string | null;
    longitude: string | null;
    accuracy: string | null;
    location_method: string | null;
    location_status: string | null;
    acknowledged_by_name: string | null;
    history: Array<{
        id: number;
        action: string;
        action_label: string;
        summary: string;
        old_value: string | null;
        new_value: string | null;
        reason: string | null;
        actor_name: string | null;
        created_at: string | null;
        created_at_label: string | null;
    }>;
    injury_occurred_at: string | null;
    injury_occurred_at_label: string | null;
    parties: IncidentParty[];
    amendments: IncidentAmendment[];
    is_read_only: boolean;
    management_layer_hidden: boolean;
    closure_blockers: string[];
    closed_at_label: string | null;
    closure_note: string | null;
    closed_by_name: string | null;
    reopen_reason: string | null;
    reopened_at_label: string | null;
    documents: {
        submitted: string | null;
        final: string | null;
    };
    finding?: string | null;
    cause_contributing_factors?: string | null;
    prevention_plan?: string | null;
    reviews?: IncidentReview[];
    follow_ups?: IncidentFollowUp[];
    reporting_records?: IncidentReportingRecord[];
};

export type IncidentPartyKind =
    "primary_client" | "client" | "employee" | "outside";

export type IncidentParty = {
    id: number;
    party_kind: IncidentPartyKind;
    party_kind_label: string;
    client_id: number | null;
    client_name: string | null;
    employee_id: number | null;
    employee_name: string | null;
    name: string | null;
    organization: string | null;
    involvement: string | null;
};

export type IncidentAmendment = {
    id: number;
    field_key: string;
    reason: string;
    original_content: string | null;
    new_content: string | null;
    author_name: string | null;
    created_at_label: string | null;
};

export type IncidentReview = {
    id: number;
    note: string;
    reviewer_name: string | null;
    created_at_label: string | null;
};

export type IncidentFollowUp = {
    id: number;
    action_type: string;
    assigned_employee_id: number | null;
    assignee_name: string | null;
    due_on: string | null;
    status: string;
    status_label: string;
    completion_note: string | null;
    completed_at_label: string | null;
};

export type IncidentReportingRecord = {
    id: number;
    reported_to: string;
    person_organization: string | null;
    reported_at_label: string | null;
    reported_by_name: string | null;
    category: string | null;
    method: string | null;
    reference_number: string | null;
    note: string | null;
};

export type IncidentPartyOption = { id: number; name: string };

export type VisitIncidentsPayload = {
    draft: IncidentDetail | null;
    submitted: IncidentListItem[];
    categories: IncidentCategoryOption[];
    acknowledgment_threshold_minutes: number;
    has_unresolved_draft: boolean;
    party_clients?: IncidentPartyOption[];
    party_employees?: IncidentPartyOption[];
};
