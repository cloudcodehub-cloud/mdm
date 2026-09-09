# UI design

## Current state (Phase 0)

The UI is the Laravel React starter kit (welcome, dashboard, auth, settings). No MDM domain redesign yet.

## Direction

- Keep screens role-aware (ADMIN, SUPERVISOR, DSP).
- Prefer clear operational workflows over decorative redesign.
- DSP flows should prioritize schedule → client → clock-in → tasks → notes/skips → handover → clock-out.
- Do not redesign in Phase 0; document intent here as modules are specified.

## Fonts

Use system/UI fonts for reliability. Production builds must not download fonts from external CDNs at build time.
