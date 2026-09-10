# DSP module

## Purpose

Support DSP (Direct Support Professional) day-to-day service delivery.

## Core DSP flow (intent)

1. View own schedule
2. Select assigned client
3. Clock in
4. Perform care-plan tasks
5. Record notes/measurements where applicable
6. Skip tasks only with mandatory reasons
7. Complete visit summary / handover
8. Clock out

## Phase 1B-2 foundation

Domain records now exist for the upcoming workflow (no DSP UI or clock-in yet):

- Care plans and task templates on the assigned client
- `scheduled_visits` for the DSP (`employee_id`) with a service date and either a shift template or explicit times
- Seeded skip reasons, including **Other** which will later require a comment

DSPs may view care plans for currently assigned clients, their own scheduled visits, and skip reasons. They cannot create or update those records in this phase.

Detailed operational screens will be added when DSP operations are implemented.
