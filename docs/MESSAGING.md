# Messaging

## Purpose

Internal operational communication for the current MDM organization: one-to-one chat, in-app notifications, and announcements.

This module is not a tenant platform. `organization_id` is reserved on conversation and announcement rows for later isolation and stays unused until multi-tenancy is introduced.

## Messages

- Available to ADMIN, SUPERVISOR, and DSP.
- Database-backed 1-to-1 conversations only. No groups, attachments, typing, presence, or external channels.
- Any active authenticated user may start a conversation with another active user.
- Inactive and terminated employee accounts cannot be messaged and cannot participate.
- Users may only open conversations they belong to.
- Unread counts are per conversation and on the Messages sidebar item.
- While MDM is open, the client polls `/inbox/activity` (no WebSockets). New message and announcement notifications can show a toast; the same `source_key` is not toasted twice in the tab.

## Announcements

- Admin may publish to Everyone, Admins, Supervisors, or DSPs across the organization.
- Supervisor may publish only to their permitted DSP caseload (`employees.supervisor_id`).
- DSP cannot publish.
- Fields: title, message, publish date, optional expiry, active/inactive, audience, read/unread.
- Visible announcements appear on the dashboard and the announcements page. Publishing creates in-app notifications for current recipients.

## Notifications

The top-bar bell lists recent in-app notifications, unread counts, mark-as-read, and mark-all-read. Clicking a notification opens its destination (conversation or announcements).
