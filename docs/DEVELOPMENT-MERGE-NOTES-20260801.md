# Development Merge Notes - axiro-base-api - 2026-08-01

## Scope reviewed

This review covers the current development updates for customer wallet/support flows, customer transaction experience, marketplace operations, demo seed integrity, media upload behavior, document generation, and marketplace contract parity.

## Verification

- `php artisan test`
- Result: 55 tests passed, 541 assertions passed.

## Repository hygiene

- `database/database.sqlite` was removed from the Git index and remains a local runtime artifact.
- `.gitignore` explicitly ignores `*.tmp` and `database/*.sqlite`.
- Runtime artifacts such as `.env`, SQLite databases, caches, logs, storage uploads, and `vendor/` must remain uncommitted.

## Development merge decision

Approved for development merge. The test suite passes and new lifecycle coverage has been added for customer support/wallet behavior, transaction experience, and demo seeder integrity.

## Release blockers

- `composer audit` still reports 28 advisories affecting 10 packages, including high severity advisories in Laravel/Symfony-related dependencies.
- This merge is not production-release clearance.
- Before release hardening, upgrade affected Composer dependencies and rerun `composer audit` plus the full test suite.
