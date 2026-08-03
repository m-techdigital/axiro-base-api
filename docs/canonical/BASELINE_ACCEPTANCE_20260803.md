# Baseline Acceptance - 2026-08-03

## Accepted Baseline

- Repository: `axiro-base-api`
- Commit: `4f8300d`
- Marketplace contract baseline: `2026-08-03.1`
- Status: `ACCEPTED`

## Scope

AXIRO Mini API remains a one-admin-many-customers marketplace API. It keeps customer auth/profile, products, offer modes, availability, transactions, payments, wallets, withdrawals, payouts, marketplace documents, notifications and audit logs in Mini scope.

Excluded parent domains remain RBAC, company, department, project, HR/payroll, accounting, BI reports, CRM reservations and generic workflow automation.

## Verification

Passed source/package gates:

- `./vendor/bin/pint --test`
- `php artisan test`
- `php scripts/check-release-package.php`

Document and withdrawal service splits are accepted as Mini-bounded parent-pattern implementations, not exact copies of AXIRO parent runtime.
