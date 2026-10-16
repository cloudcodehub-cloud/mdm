# Compliance

## Purpose

Track workforce compliance related to credentials, documents, training, and operational requirements.

## Phase 1B-1 foundation

Credential and training records now exist on employees:

- `employee_credentials` — type, issuer, issue/expiry, status
- `employee_trainings` — title, provider, hours, completion/expiry, status

Validity helpers treat a credential as currently valid when status is `active` and `expires_on` is empty or today-or-later. Completed training is currently valid under the same expiry rule.

## Phase 3D-1 compliance module

The Compliance screens summarize recorded employee credentials and training for the permitted workforce (admin: all; supervisor: assigned DSP reports). Status is derived from dates and the organization `credential_expiring_soon_days` threshold, not from a stale stored label alone.

Summary buckets: Valid, Expiring Soon, Expired, and Missing. Missing stays at zero unless a required-item catalog exists; the current data model cannot truthfully prove a credential is required but absent.

Employee attention and credential/training drill-down link to existing employee records. DSP users keep viewing their own credentials and training on the employee profile; they do not have a separate Compliance navigation item.

## Phase 3A — Hard Stop & DSP self-view

MDM 08 owns Compliance Requirement policy. Evidence (`employee_credentials` / `employee_trainings`) is separate from policy.

- `compliance_requirements` — applicability, enforcement level (informational / warning / required / Hard Stop), override policy (Non-Overrideable vs Temporary Exception)
- `compliance_temporary_exceptions` — audited Admin/HR temporary exceptions (reason, start/end, actor, timestamps); do not change underlying expired/missing state
- `ComplianceEligibilityService` — single eligibility result consumed by Scheduling assignment and Start Visit / clock-in
- Expiring Soon is a warning only and never creates a Hard Stop by itself
- Only Hard Stop / Mandatory for Service requirements block service
- Ordinary Supervisors cannot waive Hard Stops; Admin cannot bypass Non-Overrideable requirements
- DSP read-only self-view: `/my-compliance` (credentials, training, expiration warnings, eligibility). DSPs cannot modify compliance or apply overrides

Employee lifecycle, leave, and account deactivation remain distinct from compliance eligibility.
