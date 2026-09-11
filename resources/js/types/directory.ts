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
};

export type EmployeeDetail = EmployeeSummary & {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    hired_on: string | null;
    terminated_on: string | null;
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
};

export type DspScheduleOption = OptionItem & {
    assigned_client_ids: number[];
};

export type ClientScheduleOption = OptionItem & {
    client_number: string;
    supervisor_id?: number | null;
};

export type ShiftTemplateOption = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    spans_overnight: boolean;
};
