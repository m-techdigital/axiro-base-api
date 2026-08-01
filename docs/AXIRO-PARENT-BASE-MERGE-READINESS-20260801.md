# AXIRO Parent Base Merge Readiness - axiro-base-api - 2026-08-01

## Scope reviewed

This review covers the latest base synchronization from the AXIRO parent API (`mylands-api`) into the mini API. The update expands source-aligned list/query behavior, admin payment setting management, audit/payment/wallet/document controllers, and parent base parity documentation/tests.

## Verification

- `php artisan test`
- Result: 66 tests passed, 646 assertions passed.

## Alignment decisions

- Kept the mini API inside the existing `/api/v1` response envelope while adopting parent-aligned request/query/resource boundaries.
- Added payment setting history and activation to the contract because the synchronized API routes and admin UI both depend on them.
- Preserved base constraints: no RBAC/company/project/accounting/report/inventory dependency was introduced into this base.

## Development merge decision

Approved for development merge. Parent base parity, list query adoption, source selection, dependency closure, and payment setting management are covered by new tests.

## Release blockers

- `composer audit` still reports 28 advisories affecting 10 packages.
- This is not production-release clearance. Upgrade affected Composer packages and rerun audit plus the full test suite before release hardening.
