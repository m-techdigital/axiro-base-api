# Admin Base CRUD & Action Lifecycle Alignment

**Date:** 2026-08-03

## Scope

- Mini remains a one-admin-many-customers marketplace base.
- Admin CRUD should follow AXIRO parent base ownership patterns without importing parent RBAC, company, project, accounting or report dependencies.
- Product, Customer and Transaction forms use the Mini `BaseFormPage`, `BaseForm` and `BaseFormFooter` layout owner instead of ad hoc page/card/button layouts.
- Transaction detail uses base header and confirm action owner for state-changing operations.

## Parent Reference

- AXIRO parent pattern: `BaseFormPage`, `BaseForm`, `BaseListView`, `BaseTable`, declarative/centralized action ownership.
- Mini classification: `mini_bounded`.
- Reason: AXIRO parent form/list infrastructure is tied to module registry, permission graph and broad enterprise domains. Mini keeps the pattern but not those dependencies.

## Implemented

- Extended Mini `BaseFormPage` to accept `cardProps` and remain the single form page card owner.
- Product form now uses `BaseFormPage` and keeps offer-mode/product-type semantics aligned with parent decisions.
- Customer form now uses `BaseFormPage`, `BaseFormFooter` and a balanced grid for account/contact/status fields.
- Transaction form now uses `BaseFormPage`, `BaseFormFooter` and a balanced grid for lifecycle, parties, money, dates and rental-only fields.
- Transaction detail state-changing actions now require confirmation for payment confirmation and document synchronization.
- Transaction detail document synchronization now reports failures instead of silently clearing loading state.

## Guardrails

- Do not introduce a Contract module for Mini; transaction remains the lifecycle owner.
- Do not add RBAC/company/project/accounting/report dependencies just to match parent directory shape.
- Use base UI owners first; only keep bespoke layout when the base does not cover the interaction.
- State-changing admin actions must have a visible confirmation, success/error feedback and reload or local state refresh.

## Verification

- Admin lint, source closure and production build are required after UI changes.
- API full PHPUnit remains required because transaction/action contracts are shared with admin flows.
