# Testing

## Current baseline

The Laravel React starter includes feature tests for authentication, dashboard access, and settings.

Phase 1A adds PHPUnit feature tests under `tests/Feature/Domain` for roles, User ↔ Employee, supervisor relationships, clients, DSP ↔ client assignments, factories/seeders, and policies.

Phase 1B-1 adds PHPUnit feature tests for employee credentials, training, client authorizations, shift templates (including overnight 11–7 windows), related policies, and demo seed coverage.

Phase 1B-2 adds PHPUnit feature tests for care plans (active/historical), care-plan task recurrence (including custom), scheduled visits (shift template or explicit times, including overnight), skip reasons, related policies/form requests, and demo seed coverage.

Phase 3B-2A adds PHPUnit feature tests for DSP clock-in, GPS capture vs GPS-unavailable attestation, one-active-visit enforcement, idempotent visit/task creation, care-plan task instance generation, and ADMIN / SUPERVISOR / DSP visibility of active visits.

Phase 3B-2B adds PHPUnit feature tests for DSP task complete/skip, comment-required and client-refusal skips, exception creation, handover persistence, clock-out GPS captured and GPS-unavailable paths, duplicate clock-out blocking, cross-DSP denial, scheduled-visit completion, and clearing the DSP active visit.

Pest was not added: `laravel/pao` in this starter conflicts with `pestphp/pest`. Tests use the existing PHPUnit + `php artisan test` setup.

## Expectations going forward

- After substantial changes, run Laravel tests (`php artisan test` or project test script).
- Run TypeScript checks when configured (`npm.cmd run types:check`).
- Run frontend production build (`npm.cmd run build`) when frontend/config changes affect assets.
- Prefer `npm.cmd` on Windows if PowerShell blocks `npm.ps1`.
- Add domain tests as MDM modules are implemented (roles, visits, clock-in/out, permissions).

## Phase 0 checkpoint

Confirm auth tests still pass after foundation naming, URL, and font/build changes.
