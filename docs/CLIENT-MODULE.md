# Client module

## Purpose

Manage clients receiving services. Clients are **not** login users.

## Phase 1A foundation

`clients` stores:

- unique `client_number`
- name, contact information, date of birth
- address and emergency contact
- `status`: active, inactive, discharged
- notes
- assigned supervisor (`supervisor_id` → `employees`)

DSP coverage is modeled in `client_dsp_assignments`:

- one DSP can serve multiple clients
- one client can have multiple DSPs
- active vs inactive assignments
- `started_on` / `ended_on` for history

Do not store DSP or client lists as comma-separated IDs.

## Phase 1B-1 authorizations

`client_authorizations` stores payer authorizations for a client: unique authorization number, payer, service type, date window, authorized units, unit type, and status.

Admins create and update authorizations. Supervisors may view authorizations for assigned clients. DSPs may view authorizations for currently assigned clients. Authorization history is retained (no hard delete).

## Phase 1B-2 care plans

`care_plans` belong to a client and support active vs historical periods (`starts_on` / `ends_on`, status `active` / `inactive`).

`care_plan_task_templates` belong to a care plan. Recurrence values: daily, weekly, biweekly, monthly, annual, and custom (`recurrence_detail` holds the custom/future extension text).

Admins create and update care plans and task templates. Supervisors may view plans for assigned clients. DSPs may view plans for currently assigned clients. History is retained (no hard delete). Clock-in, visit-task completion, and skip capture are later work.

## Planned later

- Client management UI
- Visit-task execution against care-plan templates
