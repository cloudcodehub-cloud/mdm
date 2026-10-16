export type ComplianceRequirementRow = {
    requirement_id: number;
    code: string;
    name: string;
    source_kind: string;
    source_key: string;
    enforcement_level: string;
    enforcement_label: string;
    override_policy: string;
    override_policy_label: string;
    allows_temporary_exception: boolean;
    condition: string;
    condition_label: string;
    evidence_label: string | null;
    expires_on: string | null;
    unsatisfied: boolean;
    is_warning: boolean;
    is_hard_stop_condition: boolean;
    exception_active: boolean;
    exception: ComplianceExceptionSummary | null;
    blocks_service: boolean;
    underlying_state_unchanged: boolean;
};

export type ComplianceExceptionSummary = {
    id: number;
    requirement_id: number;
    requirement_name?: string | null;
    reason: string;
    starts_at: string;
    ends_at: string;
    created_by: string | null;
    created_at: string | null;
};

export type ComplianceEligibility = {
    eligible_for_service: boolean;
    hard_stop: boolean;
    tone: 'green' | 'yellow' | 'red' | string;
    tone_label: string;
    summary: string;
    warnings: ComplianceRequirementRow[];
    hard_stops: ComplianceRequirementRow[];
    requirements: ComplianceRequirementRow[];
    active_exceptions: ComplianceExceptionSummary[];
    block_reason: string | null;
};

export type ComplianceEvidenceItem = {
    id: number;
    kind: string;
    title: string;
    type: string;
    type_key: string;
    issuer: string | null;
    expires_on: string | null;
    completed_on?: string | null;
    status: string;
    status_label: string;
    is_warning: boolean;
    is_blocking_condition: boolean;
};
