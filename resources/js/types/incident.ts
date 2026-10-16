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
    is_read_only: boolean;
    management_layer_hidden: boolean;
};

export type VisitIncidentsPayload = {
    draft: IncidentDetail | null;
    submitted: IncidentListItem[];
    categories: IncidentCategoryOption[];
    acknowledgment_threshold_minutes: number;
    has_unresolved_draft: boolean;
};
