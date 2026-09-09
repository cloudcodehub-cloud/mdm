# Testing

## Current baseline

The Laravel React starter includes feature tests for authentication, dashboard access, and settings.

## Expectations going forward

- After substantial changes, run Laravel tests (`php artisan test` or project test script).
- Run TypeScript checks when configured (`npm.cmd run types:check`).
- Run frontend production build (`npm.cmd run build`) when frontend/config changes affect assets.
- Prefer `npm.cmd` on Windows if PowerShell blocks `npm.ps1`.
- Add domain tests as MDM modules are implemented (roles, visits, clock-in/out, permissions).

## Phase 0 checkpoint

Confirm auth tests still pass after foundation naming, URL, and font/build changes.
