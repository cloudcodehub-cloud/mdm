# Upgrade notes

## Phase 6C — Branded PDF / document output

- Server-side PDFs use `barryvdh/laravel-dompdf` (DomPDF 3). No browser process and no third-party document SaaS.
- One shared Blade document shell covers MDM mark, configured agency name, organization timezone, generated-by, confidentiality footer, and download filenames.
- First documents: Client Visit & Task Handout (scheduled visit), Completed Visit Report (completed visit / attendance detail), Employee Hours & Attendance Report (Employee Attendance and Payroll Hours report screens). Authorization matches existing visit/report policies.

## Phase 6B — Agency identity and dashboard context

- Agency identity is configuration-driven via existing `organization_settings` (`organization_name`). Admins edit **Agency Name** in General settings; it is not hard-coded in dashboard or layout UI.
- Organization timezone remains the single operational timezone and is authoritative for dashboard greeting, the top-bar clock, and future reporting/PDF timestamps. Historical UTC timestamps are not rewritten.
- Greeting and sun/moon indicators use organization-local time (morning 05:00–11:59, afternoon 12:00–16:59, evening 17:00–04:59; sun 06:00–17:59, moon 18:00–05:59). The live clock starts from a server timestamp and advances client-side.
- Browser geolocation, Maps/GPS/EVV, and branded PDF/document output remain deferred.

## Phase 6B — Access hardening (second batch)

- DSP Gate access to `ClientAuthorization` is denied. Billing/authorization records remain Admin/Supervisor caseload views only; assigned DSP HTTP payloads already omitted this ledger.
- DSP scheduled-visit detail no longer includes the workforce DSP picker (`assigned_client_ids` / other DSP identities). Replacement pickers remain on Admin/Supervisor create/edit/replace surfaces.
- Admin weekly availability override is limited to DSP employees (`EmployeePolicy::overrideWeeklyAvailability`).
- Unused account-deletion UI component was removed. `DELETE settings/profile` remains 403 for all current roles.

## Phase 6B — Access hardening (first batch)

- Supervisor and DSP login requires a linked Employee record that is allowed to sign in. Admins may still sign in without an employee profile.
- DSPs cannot open `/employees/{employee}` (including their own HR record or employee photo). Settings → Profile remains the DSP account surface.
- Assigned DSP client detail and visit care history omit authorizations, assignment history, other DSP visits, and other DSP task notes. Admin/Supervisor payloads are unchanged.
- Account self-deletion is denied for all current roles (`DELETE settings/profile` returns 403). Users, employees, and operational history are not removed by that endpoint.

## Phase 6A — Authentication, role routing, and app shell

- Post-login destination remains the shared `/dashboard` route. Role-specific experience is the dashboard payload (ADMIN / SUPERVISOR / DSP), not separate dashboard URLs.
- Sidebar navigation no longer falls back to DSP items when role is missing; active nav now matches nested routes (for example `/employees/1`).
- DSP My Availability POST endpoints require an active DSP employee. Client assignment DSP pick-lists are included only for users who can create assignments. Schedule board supervisor filters are omitted for DSP.

## Phase 5C — Employee onboarding and profile completion

- System Role and Job Title are separate. System Role (DSP, Supervisor, Admin) drives login permissions and role-aware profile completion; Job Title is the employment label.
- Employee onboarding is a staged stepper. Initial DSP availability uses the Phase 5B weekly availability model; later DSP changes still require supervisor approval.
- Licenses, TB, and physician good-health records reuse the existing credential/compliance architecture.
- Employee and Client photos use shared profile-photo handling (stored files, not base64) with initials fallback.
- Profile Completion is separate from Compliance / credential readiness.
- Completion requirements are role- and context-aware. Missing non-critical items are aggregated for Supervisor/Admin attention, not one notification per field.
- Sensitive employment/background data (including encrypted SSN) is restricted to Admin and assigned Supervisors and never appears in notification copy.

## Phase 5B — Scheduling experience polish

- Care Plan and Scheduled Visit builders expose a persistent live summary.
- Mobile uses compact Review summary instead of fixed side panel.
- Multi-service task groups remain visibly tied to Services.
- Week calendar range and columns use one shared week-boundary rule.

## Phase 5B — Scheduling workspace refinement

- A visit may contain multiple client-assigned Services. Each selected service is stored on the visit; list labels may join names for display only.
- Visit Care Plan is editable per visit (include/exclude, extra catalog, custom one-off) without changing the permanent Client Care Plan.
- Tasks are reviewed before DSP selection. Date and requested time drive DSP availability; tasks never do.
- Excluding a due Required or Critical care-plan task requires an explicit reason before scheduling.
- DSP selection uses compact availability cards. Backend ranking remains authoritative.
- Missing weekly availability is not presented as confirmed coverage. Scheduling may still be allowed under existing rules, with a caution state.

## Phase 5B — Scheduling and workforce orchestration

- Scheduling is workforce/date/time driven. Client → assigned service → date and requested window → the client’s assigned Supervisor → eligible DSP availability and workload.
- Supervisor normally comes from the client profile. Admin may override only as an exception.
- Availability and leave/time off are separate. Approved leave makes a DSP unavailable on the board without payroll or accrual logic.
- DSP availability changes require approval. Approved changes that collide with scheduled visits flag those visits; they do not silently reassign anyone.
- Task recurrence and visit recurrence are separate engines. Care-plan preview never creates `visit_tasks` and never decides who can work a shift.
- Recurring series keep historical integrity: completed and in-progress visits are not rewritten. Series edits support this visit, this and future, or the entire remaining series.
- Reassignment preserves original assignment history, including call-off/replacement reason.
- Calendar/Schedule Board supports day/week/month and DSP vs client coverage views. Drag-and-drop is deferred until base scheduling is stable.

## Phase 5A — Care workflow and task catalog

- Service is a care/program umbrella. Task is the actual DSP work. Services do not own recurrence or visit schedules.
- A service may recommend task bundles. Selecting a service does not force those tasks onto the client.
- Client service selections filter/recommend care-plan tasks by default, while Show All allows intentional cross-service task selection; filtering never removes already-selected client tasks.
- Catalog defaults remain suggestions. The client care plan owns recurrence, timing, instructions, and required/skip/note/critical flags.
- Scheduled Visit can preview which care-plan tasks are due for the selected client and date. That preview does not create `visit_tasks`.
- One-off tasks belong only to that scheduled visit and never change the permanent care plan.
- Clock-in remains the authoritative visit-task generation point (due care-plan tasks plus that visit’s one-offs).
- DSP future recurring items are preparation-only and cannot be completed early.
- DSP work is organized by client and visit. Visit tasks continue to be generated from the active care plan at clock-in; they are not a shared checklist.
- Care-note continuity is limited to the same client’s care/visit notes. Private employee HR data is not exposed.
- Contextual “Message Previous DSP” / “Contact Supervisor” reuses the existing one-to-one messaging system, with optional care context on the message.
- DSP remains action-first and mobile-first. Supervisors may configure care plans only for scoped clients (`manageCarePlan`). Existing EVV and audit timestamps remain authoritative.

## Phase 4B-1 — Visual intelligence and role dashboards

- DSP remains workflow-first, not record-first. The dashboard emphasizes the current visit action; task progress is a summary of existing `visit_tasks`, not a new workflow.
- Charts and progress visuals use real, scoped operational data only. They do not invent missing requirements or decorative series.
- Visual summaries sit above detailed lists and tables. They do not replace attendance, compliance, visit, or exception records.
- MDM frosted matte style remains restrained: mostly opaque surfaces, light-catching borders, soft shadow, and modest backdrop blur. Dialogs and popovers may frost slightly more than cards.
- Official brand palette (green → mint → teal → cyan) is the source of atmospheric and accent color. Semantic status colors stay separate.
- Existing EVV, audit, scheduling, clock-in/out, attendance, compliance, and permission logic is unchanged.

## Phase 4B-2 — DSP workflow and module experience

- DSP interfaces are action-first. Admin and Supervisor interfaces remain information-first.
- While a DSP has an active visit, that visit stays visible in the application shell using the real Visit and `visit_tasks` records. Visual progress never replaces those records.
- Mobile DSP workflow has priority: persistent continue-visit action, task cards, notes, and clock-out stay thumb-reachable.
- Teal remains the brand anchor. The product palette is broader (cyan, mint, lime, amber, coral, lavender, slate) for hover, charts, module accents, and atmosphere—not as competing page themes.
- Active navigation stays teal/cyan. Idle hover may use a contrasting coral/peach accent; active hover does not leave the teal identity.
- Module accents are restrained supporting color, not full restyles.
- Charts use real data only. Semantic status colors stay consistent (success, warning, danger, information, neutral), and color is never the only status indicator.
- Original EVV and audit timestamps must not be visually or technically replaced.
- Frosted matte style remains restrained: clearer surface hierarchy, low-opacity atmospheric depth, no heavy glassmorphism.
