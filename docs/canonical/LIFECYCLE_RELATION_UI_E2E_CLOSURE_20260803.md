# Lifecycle, relation, UI and lightweight E2E closure — 2026-08-03

## Scope

This closure keeps AXIRO Mini within one admin and many customers. It does not introduce RBAC, company, project, accounting, reports, HR or payroll dependencies.

## API ownership

- Withdrawal creation, rejection and paid confirmation use dedicated request owners.
- A customer may cancel only a `submitted` withdrawal.
- Cancellation restores held funds to available funds through `WalletLedgerService` with an idempotency key.
- Cancellation writes an audit event and is declared in the marketplace contract.
- Transaction lifecycle remains a facade over payment capture, payment plan, settlement, dispute and action-policy owners.

## Admin ownership

- Transaction customer/product selects use canonical relation configs through `BaseForm`.
- The legacy service overload in `useRelationOptions` has been removed.
- Payout, notification and wallet pages are orchestration-only owners backed by hooks, columns/config and detail modal/drawer components.
- Source gates protect relation ownership and the primary admin flow contract.

## Customer UI ownership

- Payout loading, error and retry states are owned by `PageShell`.
- A submitted withdrawal exposes a customer cancellation action.
- Parent-layout parity is checked for list/detail/form/payout/document/notification pages.
- A lightweight core-flow gate protects login, product creation, transaction creation, payment, dispute, payout and document contracts.

## Verification boundary

Source-contract gates and PHP syntax checks are required in clean archives. Full PHPUnit, Pint, lint and production builds remain mandatory in an installed local environment before merge.
