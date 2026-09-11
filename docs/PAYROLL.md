# Payroll

## Purpose

Support hours-only payroll exports derived from attendance and completed visit activity.

## Phase 3D-2 hours export

Admins select a pay-period date range on the Payroll Hours report. Rows include:

- Employee ID (`employee_number`)
- Employee Name
- Period Start
- Period End
- Completed Visit Count
- Worked Hours (decimal hours)

Worked hours use effective attendance:

- original clock-in/out when no approved correction exists
- approved adjusted times when an attendance correction exists

Original EVV clock records on `visits` are never altered.

This export does **not** include hourly rates, gross pay, taxes, deductions, overtime, or payroll processing.

Supervisors may view caseload work-hour totals. Payroll CSV export is Admin-only.
