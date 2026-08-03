# AXIRO Base Extraction Report

## Source
- Frontend: mylands-admin-report-top-goods-sqlite-final-FULL-20260729(1).zip
- Backend: mylands-api-report-top-goods-sqlite-final-FULL-20260729(1).zip

## Result
Two independent mini repositories were extracted while preserving the original React module layout and Laravel controller/request/model/service layout.

## Canonical dependency map
`User -> Product -> Transaction -> Contract`

- User only authenticates and records creator/updater ownership.
- Product has many transactions.
- Transaction belongs to one product and has at most one contract.
- Contract derives its product through transaction; no duplicated `product_id`.

## Kept
- JWT login, refresh rotation, logout, current user.
- Shared JSON response envelope.
- CRUD service factory, API interceptor, auth provider.
- Shared list/detail/async/relation hooks.
- Products, Transactions, Contracts, Dashboard.
- 401 refresh flow and 422 form error mapping.

## Removed
RBAC, roles, permissions, users CRUD, companies, departments, projects, opportunities, reservations, inventory quantity modules, accounting, commission, HR, reports, audit center, workflow automation, system operations and their routes/schema/runtime bindings.

## Final schema
- users
- refresh_tokens
- products
- transactions
- contracts
- cache / jobs support tables

Money uses `decimal(18,2)`. Transaction total is calculated by Backend as `transaction_value + service_fee - discount`.

## Lifecycle
- Product: draft, active, inactive
- Transaction: draft, pending, completed, cancelled
- Contract: draft, active, completed, cancelled

## Integrity rules
- Product with transactions cannot be deleted.
- Transaction with contract cannot be deleted.
- Contract cannot be created from cancelled transaction.
- One contract per transaction.
- Negative money and overpayment are rejected.

## Verification performed
- PHP syntax lint passed for all Backend application, migration, route and test files.
- Frontend route metadata check passed.
- Frontend/Backend source dependency scans were completed.

## Environment limitations
The execution container did not provide a Composer executable. `npm ci` also failed at the container client layer before dependency installation completed, so Laravel runtime tests and Vite lint/build could not be executed here. The repositories include the exact commands and tests required to run them in a normal local environment.

## Intentional limitations
Legacy extraction baseline was single administrator only. The current Mini baseline is one admin with many marketplace customers and includes bounded customer auth/profile, media upload, transaction documents, wallets, payouts and audit/history surfaces.

Still excluded: RBAC graph, company/department/project scope, HR/payroll, accounting posting, BI report runtime, CRM reservation/opportunity modules, parent inventory quantity modules and organization hierarchy.
