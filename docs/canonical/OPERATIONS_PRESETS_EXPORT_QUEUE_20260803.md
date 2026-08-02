# Operations Presets, Export Queue & Notification Counter

- Saved filter presets are browser-local and scoped to operations queues/settlements.
- Unread Admin notifications are exposed through the lightweight operations overview counter.
- Rental settlement exports support a queued CSV flow with status, expiry and authenticated download.
- Direct CSV export remains for small/manual use and compatibility.
- Admin Vite build splits React, Ant Design, HTTP and date dependencies into stable chunks.
- No RBAC, company/project/team, SLA engine, fraud engine or BI subsystem is introduced.
- Schema remains canonical in original migrations while the project is unreleased; before preserving live data, freeze schema and create additive migrations from this baseline.
