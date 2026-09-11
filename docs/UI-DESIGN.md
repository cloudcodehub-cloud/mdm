# UI design

## Brand

**Product name:** MDM - Magic Data Management  
**Tagline:** Streamlining Success, One Data at a Time.

Use the supplied PNG assets only. Do not redraw or reinterpret the logo. Preserve transparency and aspect ratio.

| Surface | Asset |
| --- | --- |
| Public welcome | `public/brand/mdm-logo-horizontal.png` |
| Login / auth | stacked or horizontal PNG |
| Expanded sidebar | `mdm-icon.png` plus compact **MDM** / Magic Data Management text |
| Collapsed sidebar | icon only, with tooltip |
| Mobile header | icon only |
| Favicon / app identity | `mdm-icon.png` |

Do not repeat the full logo across module cards. Use the tagline only on public and auth branding surfaces.

## Visual direction

**Frosted Clinical Minimalism:** premium, calm, modern, sophisticated, trustworthy healthcare SaaS.

Source palette from the logo: green → teal/cyan, with slate/white neutrals. Apply brand color only to primary actions, active navigation, focus, selected states, and small accents. Do not wash the application in green/teal.

Surfaces should feel mostly opaque with a faint haze: thin low-contrast borders, soft broad shadows, gentle backdrop blur. Avoid heavy glassmorphism, neon, large gradients, giant radii, and decorative motion.

**Dark mode** uses charcoal/slate bases and frosted darker panels, not pure black.

## Tokens

Centralize reusable styling in `resources/css/app.css` and shared UI components:

- Brand and semantic colors
- Light / dark surfaces
- Typography, spacing, radius, borders, shadows
- `surface-panel` / `surface-frosted`
- Buttons, form controls, tabs, tables/lists, badges, dialogs, dropdowns, toasts, navigation
- Hover, focus, selected, loading, and empty states

Reuse Tailwind and existing components. Do not duplicate module-specific styles when a shared token or component works.

## Appearance

Honor the stored Appearance preference: **System**, **Light**, **Dark**. Public, auth, and authenticated shells must all follow the same tokens.

## Interaction

Motion is for real interactivity only. Do not animate static cards on hover.

Keep transitions fast, subtle, and restrained (~150ms). Respect `prefers-reduced-motion`. Interactive items use pointer cursors; links must clearly feel clickable.

## Forms

Shared `Field` / control styles require a visible label, muted placeholder, required marker when applicable, helper text when useful, and clear focus, error, disabled, and read-only states. Placeholders are never the only label.

Example placeholders: first name “Enter first name”; employee ID “e.g. EMP-1042”; email “name@organization.com”; phone “(555) 123-4567”; search “Search employees, clients...”.

## Status colors

Keep semantic status separate from brand styling:

- Valid / Success = green
- Warning / Expiring = amber
- Critical / Expired = red
- Information = teal/blue
- Neutral = slate

## Public and auth

Unauthenticated `/` is a compact branded welcome (log in only; no public register). Authenticated `/` redirects to the dashboard. Login stays a simple, branded form with existing authentication behavior.
