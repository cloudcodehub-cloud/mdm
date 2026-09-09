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

## Planned later

- Authorizations
- Care plans
- Care-plan task templates
- Client management UI
