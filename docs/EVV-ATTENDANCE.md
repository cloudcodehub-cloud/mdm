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

`visits` is the EVV-lite execution record created when a DSP clocks in. It stores DSP, client, scheduled visit, service, `clocked_in_at` (server time), optional GPS fields, location method (`browser_gps` or `gps_unavailable`), location status (`captured`, `denied`, `unavailable`, `unsupported`), and an attestation reason when GPS is not captured. Status is `in_progress` until a later clock-out phase. A scheduled visit may have one visit row; a DSP may have one `in_progress` visit at a time.

`visit_tasks` are per-visit instances copied from currently active care-plan task templates that apply on the service date. Recurrence is applied simply (daily/custom always; weekly/biweekly/monthly/annual against the care plan start date). Generation is idempotent. Tasks remain `pending` until later complete/skip work.

Clock-out, attendance exceptions, skip capture, and handover are still later work.

`skip_reasons` is the lookup used later when a DSP skips a care-plan task:

| Name | Code | Requires comment |
|------|------|------------------|
| Client refused | `client_refused` | No |
| Not applicable | `not_applicable` | No |
| Already completed | `already_completed` | No |
| Safety concern | `safety_concern` | No |
| Client unavailable | `client_unavailable` | No |
| Equipment or supply unavailable | `equipment_unavailable` | No |
| Other | `other` | Yes (enforced later with visit tasks) |

## Planned later

- DSP clock-out
- Attendance
- Skip-reason capture on visit tasks
- Exceptions
- Handover notes

This application is standalone EVV-lite, not a Sandata connector.
