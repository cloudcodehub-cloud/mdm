# Employee module

## Purpose

Manage workforce employees who may serve as DSPs or other staff roles within MDM.

A **User** is a login account. An **Employee** is the HR/workforce profile. Supervisor, DSP, and Admin users may link to one employee. Admins do not have to have an employee profile.

System Role (`job_type`: dsp, supervisor, admin) is separate from Job Title.

Employee onboarding is staged. Repeatable education, references, work history, and security incidents are stored in related tables. Driving/application fields live on `employees`. Weekly availability reuses `dsp_weekly_availabilities`. Credentials and training remain on the existing compliance tables.

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

## Phase 1B-1 credentials and training

`employee_credentials` tracks required workforce credentials per employee (type, issuer, number, issue/expiry dates, status).

`employee_trainings` tracks training titles, provider, hours, completion, expiry, and status.

Admins create and update these records. Supervisors may view credentials and training for assigned DSP reports. DSPs may view their own. Records are retained (no hard delete) so history remains after expiry, revocation, or termination.

## Planned later

- Documents
- Compliance status related to employment
- Employee management UI
