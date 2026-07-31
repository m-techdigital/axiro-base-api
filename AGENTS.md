# AGENTS — AXIRO Mini API

## Source of truth
1. Production code and migrations.
2. Request/service/resource contracts and tests.
3. `docs/canonical` and linked ADRs.
4. Latest product decision.

## Parent alignment
AXIRO parent is the technical standard. Mini reduces module scope only and must keep the same response, validation, resource, service and audit conventions.

## Backend rules
- Controllers stay thin.
- Validation belongs in `ApiFormRequest` subclasses.
- Response envelope belongs to `ApiResponse`; pagination metadata belongs to `PaginationMeta`.
- Customer-safe output belongs in Resources/presenters, not ad hoc arrays duplicated across controllers.
- Lifecycle and integrity rules belong in services and execute inside database transactions when state changes span records.
- Preserve `/api/v1`, request/correlation metadata and marketplace contract headers.
- Do not add RBAC, company, department, project, accounting or report dependencies to Mini.
- Money uses decimal strings/decimal columns; never trust frontend totals.
- Add tests for lifecycle, security and contract changes.

## Verification
Run targeted tests, `ParentFoundationContractTest`, full PHPUnit and Pint on changed files.

## Parent list/query parity

- List endpoints should use `ListQueryRequest` and `AppliesListQuery` when their query contract matches.
- Sorting must use an allowlist and pagination must be capped centrally.
- Format changed PHP with Pint and run `ParentListFoundationContractTest` before handoff.
