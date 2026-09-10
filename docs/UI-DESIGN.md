# UI design

## Current state (Phase 2A)

Authenticated screens use a collapsible operations sidebar, a top bar with local date/time, demo weather, alerts, and the user menu, and role-aware dashboards (ADMIN, SUPERVISOR, DSP). Scheduled Visits is a live directory (list/create/edit/detail) for authorized roles.

Login uses the same MDM product treatment. Public Sign up remains disabled.

## Direction

- Keep screens role-aware (ADMIN, SUPERVISOR, DSP).
- Prefer clear operational workflows over decorative redesign.
- Aim for restrained healthcare/business polish: system typography, tight spacing, glass surfaces, and information-dense cards.
- DSP flows should prioritize schedule → client → clock-in → tasks → notes/skips → handover → clock-out.
- Clock-in is reserved on the DSP dashboard and is not functional yet.

## Fonts

Use system/UI fonts for reliability. Production builds must not download fonts from external CDNs at build time.
