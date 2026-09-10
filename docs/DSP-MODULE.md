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

## Phase 3B-1 scheduled visits

Admins (and supervisors within caseload scope) can create and edit scheduled visits. DSPs can open the Scheduled Visits list and visit detail for their own assignments, including from Today's work on the dashboard.

## Phase 3B-2A clock-in and active visits

DSPs can start their own eligible scheduled visits (today, or a window that currently includes now, including overnight). Clock-in is authorized server-side: only an active workforce DSP assigned to that scheduled visit may start it, and a DSP may have only one active visit at a time. Repeat submissions reuse the existing visit and do not duplicate visit-task rows.

Clock-in stores the DSP, client, scheduled visit, service, server timestamp, optional browser GPS (latitude, longitude, accuracy), location method, and location status. If GPS is denied or unavailable, coordinates are left null and a reason is required. Starting a visit sets the scheduled visit status to `in_progress` and opens the Active Visit screen with pending care-plan task instances.

## Phase 3B-2B active visit completion

DSPs complete or skip visit-task instances on the Active Visit screen. Completing a task records status, server time, and an optional note on the `visit_tasks` row only — never on the care-plan template. Skip requires a seeded skip reason. `Other` (`requires_comment`) and **Client refused** require an explanation. Required-task skips create a `critical_task_skipped` exception; client refusal also creates a `client_refusal` exception.

Visit notes and handover notes persist on the visit. Clock-out uses a review step, a new GPS request (or GPS-unavailable attestation), and a server timestamp. Unfinished required tasks must be acknowledged; they stay pending and create an `other_visit_exception`. Successful clock-out marks the visit and scheduled visit `completed`, stores end coordinates when captured, and clears the DSP’s active-visit state. Consecutive scheduled visits stay separate records.

Defer is not part of this workflow.
