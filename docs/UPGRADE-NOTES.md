# Upgrade notes

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
