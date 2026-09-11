# Upgrade notes

## Phase 5A — Care workflow and task catalog

- Service is a care/program umbrella. Task is the actual DSP work. Services do not own recurrence or visit schedules.
- A service may recommend task bundles. Selecting a service does not force those tasks onto the client.
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
