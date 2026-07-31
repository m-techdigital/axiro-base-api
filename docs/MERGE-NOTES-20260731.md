# Merge Notes - axiro-base-api - 2026-07-31

## Merge scope

This merge captures the current development updates for customer session hardening, profile avatar upload, wallet visibility, validation responses, and marketplace contract parity.

## Verification completed

- `php artisan test`
- Result: 50 tests passed, 515 assertions passed.

## Development acceptance

The code is acceptable to merge into the active development branch. The update includes lifecycle and integrity coverage through new feature tests:

- `tests/Feature/CustomerAuthenticationHardeningTest.php`
- `tests/Feature/CustomerProfileAvatarAndValidationTest.php`
- `tests/Feature/CustomerWalletVisibilityTest.php`
- `tests/Feature/MarketplaceContractParityTest.php`

## Known follow-up items

- `composer audit` reports security advisories in transitive dependencies, including high severity advisories affecting Laravel/Symfony packages.
- This merge should not be treated as production-release clearance until dependency upgrades are completed and audit is rerun.
- Local runtime files such as `.env`, `database/database.sqlite`, caches, logs, storage uploads, and `vendor/` remain ignored and must not be committed.

## Merge decision

Proceed with development merge. Track dependency remediation separately before production release.
