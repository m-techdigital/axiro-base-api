# AXIRO Mini Parent Base Parity v66.36

This pass corrected a key issue in earlier sync work: several Mini components had parent-like names but not parent-like behavior.

## Corrected Admin base owners

- Added canonical `BaseBreadcrumb` and `BaseFormModal`.
- Expanded `BasePageHeader` to own breadcrumbs, reload, declarative actions and action-form modal lifecycle.
- Expanded `BaseFilter` with grouped filters, date/date-range controls, change/search/reset events and sort ownership.
- Expanded `BaseForm` with backend field-error mapping and scroll-to-first-error.
- Expanded `BaseListView` with optional canonical header, statistics and async state.
- Expanded `BaseTable` with pagination-meta adaptation and page callbacks.
- Hardened modal/body/table/filter CSS at the base layer.

## Corrected API query compatibility

- `ListQueryRequest` now accepts `q`, `limit` and `sort=field:direction` aliases.
- Added safe `filters()` extraction.
- `AppliesListQuery` now supports mapped exact filters and explicit custom-filter callbacks.
- Added `ParentBaseParityContractTest`.

## Explicitly excluded

Permission graph, company/project context, saved views, generic workflow forms and relation cascade engines remain excluded because Mini currently has one admin operator and multiple customers.
