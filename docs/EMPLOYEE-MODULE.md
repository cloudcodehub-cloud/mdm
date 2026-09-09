# Employee module

## Purpose

Manage workforce employees who may serve as DSPs or other staff roles within MDM.

A **User** is a login account. An **Employee** is the HR/workforce profile. Supervisor and DSP users may link to one employee. Admins do not have to have an employee profile.

## Phase 1A foundation

`employees` stores:

- unique `employee_number`
- optional linked `user_id`
- name, email, phone, date of birth
- address and emergency contact
- hire date (`hired_on`) and optional `terminated_on`
- `employment_status`: active, inactive, terminated
- `job_title` and `job_type` (supervisor, dsp, other)
- `supervisor_id` (another employee)
- notes

Records are not hard-deleted. Use status for lifecycle and soft deletes if a row is archived.

## Planned later

- Credentials
- Documents
- Training records
- Compliance status related to employment
- Employee management UI
