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
    status?: string;
    status_label?: string;
    tone?: string;
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

/** MDM 03 Archive Pending: accepted archive request waiting on live work (null otherwise). */
export type ArchivePendingInfo = {
    requested_at: string | null;
    requested_by: string | null;
    reason: string | null;
    blockers: Array<{ type: string; label: string; count: number }>;
    message: string;
};

export type EmployeeDetail = EmployeeSummary & {
    archive_pending?: ArchivePendingInfo | null;
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
    status_reason?: string | null;
    status_changed_at?: string | null;
    status_changed_by?: string | null;
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
    type_key?: string;
    name: string;
    issuer: string | null;
    credential_number: string | null;
    issued_on: string | null;
    expires_on: string | null;
    status: string;
    status_label: string;
    verification_status?: string;
    verification_status_label?: string;
    verified_at?: string | null;
    rejected_at?: string | null;
    verification_note?: string | null;
    notes: string | null;
    has_document?: boolean;
    pending_review?: boolean;
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
    verification_status?: string;
    verification_status_label?: string;
    verified_at?: string | null;
    rejected_at?: string | null;
    verification_note?: string | null;
    notes: string | null;
    pending_review?: boolean;
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
    archive_pending?: ArchivePendingInfo | null;
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
    preparation?: {
        service_type?: string | null;
        location_label?: string | null;
        supervisor_name?: string | null;
        visit_instructions?: string | null;
        scheduled_time?: string | null;
        service_date?: string | null;
    };
};

export type DspClientAccess = {
    level: 'none' | 'preparation' | 'full' | string;
    level_label: string;
    allows_history: boolean;
    allows_full_profile: boolean;
};

export type AuthorizationUsage = {
    authorization_id: number;
    authorized_units: number;
    unit: string;
    unit_label: string;
    actual_used: number;
    projected_future: number;
    projected_total: number;
    remaining: number;
    days_to_expiry: number | null;
    approaching_expiration: boolean;
    capacity_pressure: boolean;
    hard_block: boolean;
    expired: boolean;
    warnings: string[];
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
    unit_value?: string;
    status: string;
    status_label: string;
    notes?: string | null;
    usage?: AuthorizationUsage;
};

export type CarePlanReviewState = {
    status: string;
    status_label: string;
    review_on: string | null;
    days_until_review: number | null;
    is_overdue: boolean;
    is_approaching: boolean;
    warning: string | null;
};

export type CarePlanRecord = {
    id: number;
    title: string;
    revision_number?: number;
    supersedes_care_plan_id?: number | null;
    starts_on: string | null;
    ends_on: string | null;
    review_on?: string | null;
    review?: CarePlanReviewState;
    status: string;
    status_label: string;
    notes: string | null;
    revision_reason?: string | null;
    effective_at?: string | null;
    timed_check_interval_minutes?: number | null;
    supervision_instruction?: string | null;
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
    starts_at_iso?: string | null;
    shift_template_id?: number | null;
    supervisor_id?: number | null;
    client_service_location_id?: number | null;
    service_location_label?: string | null;
    service_latitude?: number | null;
    service_longitude?: number | null;
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
    duration_label?: string | null;
    exception_count?: number;
    open_exception_count?: number;
    series_id?: number | null;
    assignments?: Array<{
        id: number;
        employee_name: string;
        kind: string;
        reason: string | null;
        assigned_at?: string | null;
        ended_at?: string | null;
    }>;
    availability_overrides?: Array<{
        id: number;
        employee_id: number;
        employee_name: string | null;
        current_assignment: boolean;
        conflict_kind: 'leave' | 'unavailable' | string;
        conflict_summary: string;
        override_reason: string;
        overridden_by_name: string | null;
        overridden_at_label: string;
        acknowledged: boolean;
        acknowledged_at_label: string | null;
        acknowledged_by_name: string | null;
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
    assigned_visit_tasks?: Array<{
        key: string;
        title: string;
        instructions?: string | null;
        is_required?: boolean;
        is_critical?: boolean;
        source?: string;
        source_label?: string | null;
    }>;
    handoff_pending?: boolean;
    recorded_visit?: import('./visit').ActiveVisitRecord | null;
    visit_segments?: import('./visit').ActiveVisitRecord[];
};

export type DspScheduleOption = OptionItem & {
    assigned_client_ids: number[];
};

export type ClientScheduleOption = OptionItem & {
    client_number: string;
    supervisor_id?: number | null;
    supervisor_name?: string | null;
    services?: Array<{ id: number; name: string; slug: string }>;
    service_locations?: Array<{
        id: number;
        name: string;
        is_primary: boolean;
    }>;
    default_service_location_id?: number | null;
};

export type ShiftTemplateOption = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    spans_overnight: boolean;
};

export type SupervisorCandidate = EmployeeSummary & {
    login_role: string | null;
    login_role_label: string | null;
};

export type SupervisorRoleEvent = {
    action: string;
    previous_role: string | null;
    new_role: string | null;
    actor_user_id: number;
    actor_name: string;
    at: string;
    replacement_employee_id?: number | null;
    replacement_name?: string | null;
    employee_ids?: number[];
    client_ids?: number[];
};

export type SupervisorResponsibilities = {
    dsps: EmployeeSummary[];
    clients: ClientSummary[];
    dsp_count: number;
    client_count: number;
    other_report_count: number;
    pending_availability_count: number;
    pending_time_off_count: number;
    pending_attendance_correction_count: number;
    open_exception_count: number;
    blocks_revocation: boolean;
};
