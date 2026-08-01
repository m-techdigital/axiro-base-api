# AXIRO Mini Parent Develop Neutral Sync v66.45

## Baselines

- Mini Admin: `axiro-base-admin-main-20260801.zip`
- Mini API: `axiro-base-api-main-20260801.zip`
- Storefront: `mbn-react-main-20260801.zip`
- AXIRO Admin develop: `mylands-admin-develop-20260801-d0af27d9.zip`
- AXIRO API develop: `mylands-api-develop-20260801-98fe973.zip`

## Scope rule

Mini remains one admin with many customers. This sync copies only dependency-closed and domain-neutral parent foundations. RBAC, company, project, team, Accounting, Reports, HR and approval-domain dependencies are not imported.

## Admin exact-source additions

- `BaseCheckbox`
- `BaseHeaderFilters`
- `BaseListInput`
- `BaseNumberFormatter`
- `BaseViewModeSwitch`
- `BaseWidgetGrid`
- `FieldContainer`
- `BaseCardStatistics` and its CSS
- `useTableSummary`
- `format.js`
- `date.js`
- `options.js`
- `resolveFieldValue.js`
- `normalizeDynamicListErrors.js`
- `formDefaults.js`
- `formErrors.js`
- shared `ACTIONS` constants needed by `BaseCheckbox`

The components, hook and utilities above are copied from the current parent develop source. SHA-256 values are recorded in `docs/canonical/parent-develop-neutral-sync-v66.45.json`.

## Admin CSS alignment

The parent CSS blocks for these neutral owners were mechanically extracted from `src/index.css` into:

- `src/styles/primitives/parent-develop-neutral.css`

The extraction includes:

- `BaseViewModeSwitch`
- `BaseHeaderFilters`
- `BaseWidgetGrid`

The file is imported by the Mini global CSS entry. It is not a redesign.

## API exact-source additions

- updated `AuditPayloadSanitizer`
- `DatabaseExpressions`

## API bounded adapters

The following retain the parent rules but extend Mini `ApiFormRequest` so validation errors keep the Mini canonical response envelope:

- `DateRangeRequest`
- `OptionalNotesRequest`
- `RequiredNotesRequest`
- `ExportFiltersRequest`

## Explicit exclusions

Not imported in this iteration:

- full parent `BaseRepository` and `Filterable`/`Sortable`, because they require company scope and parent domain contracts;
- `BaseEditor` and upload owners, because their dependency closure requires Tiptap, file services, file resources and authorization;
- `SavedViewsBar`, because it requires persisted saved views and the parent module registry;
- parent `useModule` and `useCapabilities`, because they require RBAC/company context;
- parent `NotificationMetadata` and `ApplicationPath`, because most entries target domains absent from Mini.

## Verification performed

Admin:

- `npm run check:all` passed;
- `npm run build` passed with the existing Vite large chunk warning;
- local and alias import closure passed;
- package imports are declared;
- renderer barrel closure passed;
- manifest hashes passed;
- extracted CSS braces are balanced.

API:

- PHP syntax checks passed for all added or changed PHP files;
- JSON manifest parsed successfully;
- a contract test was added at `tests/Feature/ParentDevelopNeutralFoundationV6645Test.php`.

API local verification passed with `php artisan test`.
