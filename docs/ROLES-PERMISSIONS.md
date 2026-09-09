# Roles and permissions

## Initial roles

- **ADMIN** — full administrative and HR/operations access
- **SUPERVISOR** — assigned DSPs/clients, visits, attendance, exceptions, progress, handovers, operational issues
- **DSP** — own schedule, assigned client selection, clock-in/out, care-plan tasks, notes/measurements, mandatory skip reasons, visit summary/handover

## Rules

- Enforce permissions with Policies/Gates on the server.
- Never rely on UI hiding alone for security.
- Scope supervisor access to assigned DSPs and clients once assignment models exist.
- Scope DSP access to their own schedule, visits, and related records.

## Account provisioning note

Production accounts should be admin-provisioned. Unrestricted public self-registration is not the intended long-term model.

Details for module-specific permissions will be added as features are designed.
