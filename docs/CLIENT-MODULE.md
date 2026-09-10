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

## Planned later

- Care plans
- Care-plan task templates
- Client management UI
