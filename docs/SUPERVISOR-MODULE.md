# Supervisor module

## Purpose

Support SUPERVISOR users who manage and monitor assigned DSPs and clients.

## Caseload

Supervisor visibility is limited to their permitted caseload:

- Assigned DSPs (`employees.supervisor_id`)
- Assigned clients (`clients.supervisor_id`)
- Scheduled visits for those DSPs or clients, or visits listing the supervisor of record
- Visit execution records and structured `visit_exceptions` on that same caseload

Admins may view any caseload. DSPs cannot open supervisor operations, exception review, or the supervisor directory.

## Phase 3C — supervisor operations

The Operations board (`/operations`) is the live caseload board. Times and “today” use the organization timezone from Settings.

The board shows:

- Assigned DSPs and clients
- Today’s scheduled visits
- Currently active visits
- Completed visits for today
- Visit/task progress
- Skipped tasks
- Handover notes
- Open GPS/location exceptions, client refusals, critical task skips, and unfinished-task exceptions

Operational status on each visit (when it can be determined):

- Scheduled
- In Progress
- Completed
- Late (scheduled start has passed with no clock-in)
- Attention needed (still in progress after the scheduled end)
- Exception (open structured exception)

Supervisors may open a permitted visit for monitoring (DSP, client, service, scheduled time, actual clock-in/out, status, location, tasks, notes, handover, related exceptions). They cannot change original DSP clock events, complete tasks, or clock out.

## Exception review

Authorized supervisors and admins review structured `visit_exceptions` (`/visit-exceptions`):

- View open (and reviewed) exceptions on their caseload
- Mark reviewed, with optional review notes
- Resolve, with optional resolution notes
- Original exception type, message, context, and history are preserved (`status_history`)

Out-of-scope supervisors cannot open another supervisor’s caseload exceptions.

## Supervisor directory (Admin)

`/supervisors` is an Admin directory of existing supervisor employees (not a second Employee CRUD). It shows status, assigned DSP/client counts, and a short operational summary. Admin may open supervisor detail to see that supervisor’s caseload board. Employee create/edit remains on Employees.

Payroll, messaging, and a full attendance engine are out of scope for this phase.
