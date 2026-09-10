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

Overnight support: when end time is earlier than or equal to start time, `ShiftTemplate` treats the end as the next day and duration includes the midnight crossing. Scheduled visits are not created in this phase.

## Planned later

- Scheduled shifts / visits
- DSP clock-in / clock-out
- Attendance
- EVV-style visit records
- Visit tasks
- Skip reasons
- Exceptions
- Handover notes

This application is standalone EVV-lite, not a Sandata connector.
