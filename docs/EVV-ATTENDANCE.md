# Attendance / EVV-lite

## Purpose

Track scheduled shifts, visits, clock-in/out, attendance, and EVV-style visit records without Sandata integration.

## Phase 1B-1 shift templates

`shift_templates` defines reusable shift windows. Demo seed includes:

| Name | Code | Starts | Ends | Notes |
|------|------|--------|------|--------|
| 7–3 | `day_7_3` | 07:00 | 15:00 | Same calendar day |
| 3–11 | `evening_3_11` | 15:00 | 23:00 | Same calendar day |
| 11–7 | `overnight_11_7` | 23:00 | 07:00 | Ends next calendar day |

Overnight support: when end time is earlier than or equal to start time, `ShiftTemplate` treats the end as the next day and duration includes the midnight crossing.

## Phase 1B-2 scheduled visits and skip reasons

`scheduled_visits` stores a planned visit: client, DSP employee, optional supervisor, service date, service type, status (`scheduled`, `in_progress`, `cancelled`, `completed`), and notes. Timing is either a linked shift template or explicit `starts_at` / `ends_at` (explicit overnight windows use the same next-day rule).

## Phase 3B-1 functional scheduling

Admins and in-scope supervisors manage scheduled visits from the Scheduled Visits screens (list, add, edit, detail). Filters: service date, client, DSP, and status. Only active DSP employees can be given a `scheduled` status. Overnight template and custom windows remain valid when end time is earlier than start time. Overlapping scheduled windows for the same DSP are rejected.

## Phase 3B-2A clock-in and visit execution records

`visits` is the EVV-lite execution record created when a DSP clocks in. It stores DSP, client, scheduled visit, service, `clocked_in_at` (server time), optional GPS fields, location method (`browser_gps` or `gps_unavailable`), location status (`captured`, `denied`, `unavailable`, `unsupported`), and an attestation reason when GPS is not captured. Clock-out stores `clocked_out_at` (server time), optional end GPS, and visit/handover notes. Status becomes `completed` on clock-out. A scheduled visit may have one visit row; a DSP may have one `in_progress` visit at a time.

`visit_tasks` are per-visit instances copied from currently active care-plan task templates that apply on the service date. Recurrence is applied simply (daily/custom always; weekly/biweekly/monthly/annual against the care plan start date). Generation is idempotent. Tasks move from `pending` to `completed` or `skipped` (with skip reason and comment when required). Completing a visit task never updates the care-plan template.

`visit_exceptions` is the structured exception foundation for this workflow (`gps_unavailable`, `client_refusal`, `critical_task_skipped`, `other_visit_exception`). Exception statuses are `open`, `reviewed`, and `resolved`. Supervisors and admins review and resolve in-scope exceptions without changing the original exception message, type, or context.

Clock-out, skip-reason capture, handover notes, and exception creation are implemented for the DSP active-visit workflow. Messaging remains later work.

`skip_reasons` is the lookup used when a DSP skips a care-plan task:

| Name | Code | Requires comment |
|------|------|------------------|
| Client refused | `client_refused` | No (explanation still required at skip capture) |
| Not applicable | `not_applicable` | No |
| Already completed | `already_completed` | No |
| Safety concern | `safety_concern` | No |
| Client unavailable | `client_unavailable` | No |
| Equipment or supply unavailable | `equipment_unavailable` | No |
| Other | `other` | Yes (enforced with visit tasks) |

## Phase 3D-1 attendance

Attendance is derived from scheduled visits, visit clock records, visit exceptions, and approved corrections. It is not a Sandata export.

- Admin sees all attendance. Supervisors see permitted caseload only. DSPs see their own records.
- Times and late/missed status use the organization operational timezone from SettingsService. Stored UTC clock timestamps are not rewritten.
- Statuses: Scheduled, In Progress, Completed, Late, Missed, Exception, Manually Adjusted. Late is past scheduled start without a visit and still within the window; Missed is past scheduled end without a visit. There is no grace-period engine.
- Supervisors cannot edit clock-in/out. They may submit a correction request (corrected start and/or end, mandatory reason, optional note) for an in-scope visit.
- Admin approves or rejects pending requests, or applies a correction directly with a mandatory reason. Original DSP clock events remain on the visit. Approved values are stored on `attendance_corrections` and used for effective duration.

## Planned later

- Messaging

This application is standalone EVV-lite, not a Sandata connector.
