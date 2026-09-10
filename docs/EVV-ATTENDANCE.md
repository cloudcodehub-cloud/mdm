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

`scheduled_visits` stores a planned visit: client, DSP employee, optional supervisor, service date, service type, status (`scheduled`, `cancelled`, `completed`), and notes. Timing is either a linked shift template or explicit `starts_at` / `ends_at` (explicit overnight windows use the same next-day rule). Clock-in/out is not implemented yet.

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

- DSP clock-in / clock-out
- Attendance
- EVV-style visit records
- Visit tasks
- Skip-reason capture on visit tasks
- Exceptions
- Handover notes

This application is standalone EVV-lite, not a Sandata connector.
