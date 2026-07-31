# AGENTS
- Keep one canonical schema and service per business concept.
- Do not add RBAC, company, department, project, accounting, report, reservation, or inventory dependencies to this base.
- Money uses decimal(18,2); Backend calculates transaction total.
- Status fields use the documented finite values.
- Preserve API response envelope and `/api/v1` versioning.
- Add tests for every lifecycle or integrity change.
- Format only changed files.
