export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: {
        prev: string | null;
        next: string | null;
    };
};

export type OptionItem = {
    id: number;
    name: string;
    employee_number?: string;
    email?: string;
    role?: string;
};

export type EmployeeSummary = {
    id: number;
    employee_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    job_type: string;
    job_type_label: string;
    employment_status: string;
    employment_status_label: string;
    supervisor_name: string | null;
    has_login: boolean;
    photo_url?: string | null;
    initials?: string;
};

export type ProfileCompletionItem = {
    key: string;
    label: string;
    category: string;
    complete: boolean;
    severity: 'critical' | 'attention';
    href: string | null;
};

export type ProfileCompletion = {
    percent: number;
    completed: number;
    missing: number;
    total: number;
    critical_missing: number;
    items: ProfileCompletionItem[];
    summary: string;
};

export type EducationRecord = {
    level: string;
    institution_name: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    graduated: boolean | null;
    years_completed: number | null;
    degree: string | null;
};

export type ReferenceRecord = {
    name: string | null;
    address: string | null;
    home_phone: string | null;
    work_phone: string | null;
    relationship: string | null;
};

export type WorkHistoryRecord = {
    started_on: string | null;
    ended_on: string | null;
    job_title: string | null;
    employer: string | null;
    employer_phone: string | null;
    employer_address: string | null;
    reason_for_leaving: string | null;
    job_duties: string | null;
};

export type SecurityIncidentRecord = {
    incident: string | null;
    city_state: string | null;
    charge: string | null;
};

export type AvailabilityDayRecord = {
    weekday: number;
    is_available: boolean;
    starts_at: string | null;
    ends_at: string | null;
    preferred_daypart: string | null;
};

export type EmployeeDetail = EmployeeSummary & {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    home_phone?: string | null;
    cell_phone?: string | null;
    alternate_phone?: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    previous_address_line_1?: string | null;
    previous_city?: string | null;
    previous_state?: string | null;
    previous_postal_code?: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    hired_on: string | null;
    terminated_on: string | null;
    employment_type?: string | null;
    preferred_shift_type?: string | null;
    desired_hours_per_week?: number | null;
    willing_long_term?: boolean | null;
    willing_short_term?: boolean | null;
    willing_pets?: boolean | null;
    willing_smoke?: boolean | null;
    how_heard?: string | null;
    employment_interest?: string | null;
    has_drivers_license?: boolean | null;
    license_state?: string | null;
    license_number?: string | null;
    vehicle_make_year?: string | null;
    insurance_company?: string | null;
    insurance_policy_number?: string | null;
    has_moving_violations?: boolean | null;
    moving_violations_description?: string | null;
    license_ever_suspended?: boolean | null;
    license_suspension_explanation?: string | null;
    may_contact_current_employer?: boolean | null;
    ohio_resident_5_years?: boolean | null;
    residence_history?: string | null;
    used_other_names?: boolean | null;
    other_names?: string | null;
    ssn_masked?: string | null;
    has_ssn?: boolean;
    alternate_ssn_masked?: string | null;
    has_conviction?: boolean | null;
    security_comments?: string | null;
    incidents?: SecurityIncidentRecord[];
    educations?: EducationRecord[];
    references?: ReferenceRecord[];
    work_histories?: WorkHistoryRecord[];
    availability_days?: AvailabilityDayRecord[];
    supervisor_id: number | null;
    notes: string | null;
    user_id: number | null;
    login_email: string | null;
    login_role: string | null;
};

export type CredentialRecord = {
    id: number;
    type: string;
    name: string;
    issuer: string | null;
    credential_number: string | null;
    issued_on: string | null;
    expires_on: string | null;
    status: string;
    status_label: string;
    notes: string | null;
    has_document?: boolean;
};

export type TrainingRecord = {
    id: number;
    title: string;
    provider: string | null;
    completed_on: string | null;
    expires_on: string | null;
    hours: string | number | null;
    status: string;
    status_label: string;
    notes: string | null;
};

export type ActivityRecord = {
    id: string;
    title: string;
    detail: string;
    occurred_on: string;
};

export type ClientSummary = {
    id: number;
    client_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    city: string | null;
    state: string | null;
    status: string;
    status_label: string;
    supervisor_name: string | null;
    active_dsp_count: number;
    photo_url?: string | null;
    initials?: string;
};

export type ClientDetail = ClientSummary & {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    postal_code: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    supervisor_id: number | null;
    notes: string | null;
    care_services?: Array<{ id: number; name: string; slug: string }>;
};

export type AuthorizationRecord = {
    id: number;
    authorization_number: string;
    payer: string;
    service_type: string;
    starts_on: string | null;
    ends_on: string | null;
    authorized_units: string | number;
    unit: string;
    status: string;
    status_label: string;
};

export type CarePlanRecord = {
    id: number;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    status: string;
    status_label: string;
    notes: string | null;
    tasks: Array<{
        id: number;
        catalog_item_id?: number | null;
        title: string;
        instructions?: string | null;
        recurrence: string;
        recurrence_label?: string;
        recurrence_detail?: string | null;
        weekdays?: number[] | null;
        interval_weeks?: number | null;
        preferred_timing?: string | null;
        preferred_timing_label?: string | null;
        is_required: boolean;
        note_required?: boolean;
        can_skip?: boolean;
        is_critical?: boolean;
    }>;
};

export type AssignmentRecord = {
    id: number;
    employee_id: number;
    dsp_name: string;
    dsp_number: string;
    status: string;
    status_label: string;
    started_on: string | null;
    ended_on: string | null;
    notes: string | null;
    is_active: boolean;
};

export type VisitRecord = {
    id: number;
    service_date: string | null;
    service_type: string;
    services?: Array<{ id: number; name: string; slug: string }>;
    status: string;
    status_label: string;
    time_label: string;
    spans_overnight?: boolean;
    dsp_name: string;
    client_name?: string;
    client_id?: number;
    employee_id?: number;
    shift_name: string | null;
    supervisor_name?: string | null;
    notes?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    shift_template_id?: number | null;
    supervisor_id?: number | null;
    client?: {
        id: number;
        name: string;
        client_number: string;
    };
    employee?: {
        id: number;
        name: string;
        employee_number: string;
    };
    supervisor?: {
        id: number;
        name: string;
    } | null;
    shift_template?: {
        id: number;
        name: string;
    } | null;
    active_visit_id?: number | null;
    visit_phase?: 'upcoming' | 'eligible' | 'active' | 'completed' | 'cancelled';
    start_unavailable_reason?: string | null;
    needs_attention?: boolean;
    attention_reason?: string | null;
    series_id?: number | null;
    assignments?: Array<{
        id: number;
        employee_name: string;
        kind: string;
        reason: string | null;
        assigned_at?: string | null;
        ended_at?: string | null;
    }>;
    one_off_tasks?: Array<{
        id?: number;
        catalog_item_id?: number | null;
        title: string;
        instructions?: string | null;
        note_required?: boolean;
        is_required?: boolean;
    }>;
    task_overrides?: Array<{
        care_plan_task_template_id: number;
        included: boolean;
        exclusion_reason?: string | null;
    }>;
    recorded_visit?: {
        id: number;
        status: string;
        clocked_in_at_label: string | null;
        clocked_out_at_label: string | null;
        duration_label: string | null;
        location_status_label: string | null;
        visit_notes: string | null;
        handover_note: string | null;
        task_summary: {
            total: number;
            completed: number;
            skipped: number;
            pending: number;
        };
        tasks: Array<{
            id: number;
            title: string;
            status: string;
            status_label: string;
        }>;
        exceptions: Array<{ id: number }>;
    } | null;
};

export type DspScheduleOption = OptionItem & {
    assigned_client_ids: number[];
};

export type ClientScheduleOption = OptionItem & {
    client_number: string;
    supervisor_id?: number | null;
    supervisor_name?: string | null;
    services?: Array<{ id: number; name: string; slug: string }>;
};

export type ShiftTemplateOption = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    spans_overnight: boolean;
};
