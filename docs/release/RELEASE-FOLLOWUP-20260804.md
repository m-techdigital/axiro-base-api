# Release Follow-up — 2026-08-04

## Scope

- Giữ mô hình Mini bounded: một admin, nhiều khách hàng.
- Không thêm RBAC, company, department, HR, accounting, project hoặc report runtime.
- Contract vẫn tương thích `2026-08-04.1`; bổ sung `lifecycle.guidance` là field mở rộng.

## Source changes

- Admin không còn ép toàn bộ `antd/**` vào một `antd-core` dùng chung; Rollup có thể giữ component chỉ dùng ở route lazy trong route chunk.
- Bundle analyzer đọc Vite manifest để đo initial JS/CSS closure, không chỉ nhìn chunk lớn nhất.
- API lifecycle trả guidance về khoản cần thanh toán, hạn bàn giao, tiền thuê/cọc/khấu trừ/hoàn lại và trạng thái tranh chấp.
- Document template đã dùng không thể tạo nhánh version từ mẫu cũ khi đã có successor.
- Admin document smoke có mutation thật để xác nhận version tăng và `supersedes_template_id`.
- MBN purchase detail dùng payment timeline owner riêng; hai stylesheet presentation lớn đã thành import-only manifests.
- Release runner ghi step hiện tại, log lỗi và JSON summary.

## Verification status

Runtime verification đã chạy lại trên working tree sau follow-up:

- API full PHPUnit: pass `116 tests / 1042 assertions`.
- API Pint và maintainability guard: pass.
- Admin `check:all`: pass.
- Admin `build:analyze`: pass, có cảnh báo budget thật `initial JS 1388.9 KB`, largest bundled vendor asset `1083.2 KB`.
- MBN build, lint, release readiness và API contract: pass.
- Marketplace contract JSON đồng bộ SHA-256 trên API/Admin/MBN.
- DOCX structural integrity: pass.

Browser smoke/E2E mutation vẫn là gate riêng trước merge/tag chính thức nếu cần chứng thực runtime qua UI thật.


## All-tasks closure follow-up

- Admin defers transaction, payout and document-template modal/editor owners with `React.lazy`; the document list no longer imports `BaseForm`/`BaseModal` eagerly.
- Admin bundle analysis now writes `dist/bundle-report.json`, measures initial and lazy-route closures, and release mode fails over budget unless an explicit bundle waiver is recorded.
- API adds customer-isolation regression coverage for wallet and generated documents, plus shared rental money/deadline guidance checks for Admin and customer surfaces.
- MBN purchase policy no longer hard-codes refund percentages or deadlines that are not owned by the transaction contract.
- React/AntD warning guards prevent deprecated `Space direction`, `Alert message`, and index-based table keys from returning.
- Runtime build/PHPUnit/browser results must be regenerated after these workspace changes; previous accepted evidence remains historical only.

## Closure update - 2026-08-04 (initial closure, responsive browser and payout isolation)

- Admin shell and login are now lazy entry owners; AntD icons and rc packages are no longer forced into shared manual chunks. Fresh `build:analyze` measured initial JS at `1388.9 KB`, still over budget but improved from the previous `1463.9 KB`.
- MBN browser core now runs desktop, tablet and mobile layout checks and fails on horizontal overflow for product detail, purchase list and account profile.
- Customer payout isolation is covered: another customer cannot see or cancel a withdrawal outside their scope.
- MBN payout state presentation now distinguishes submitted/held, approved, paid, rejected and customer-cancelled money states.
- `composer release:all` requires DOCX render output by default and records actual Git HEAD values when executed inside real repositories.
- Commit/push hashes remain pending until these ZIP contents are applied to the three connected Git repositories and pushed successfully.

## Final closure follow-up

- Admin bootstrap imports `ConfigProvider` from the direct AntD subpath and no longer mounts the unused AntD `App` provider. This is intended to reduce the actual initial dependency graph, not merely rename chunks.
- `composer release:all` now requires clean, pushed source repositories by default and records the exact pre-release Git commits.
- `composer release:finalize-evidence` updates all three baseline evidence files only after those source commits are clean and synchronized with their upstream branches. The evidence update must then be committed and pushed separately.
- Build, PHPUnit, source guards, MBN build/checks and Admin bundle measurement were regenerated after these source changes. Browser smoke, transactional E2E and DOCX visual render still require the full release environment.

## Evidence correction - 2026-08-04

- Customer payout isolation regression is included in the full API suite and passed with 116 tests / 1033 assertions.
- API release package guard now catches untracked `.phpunit.result.cache`; release runner deletes PHPUnit cache after the test step, also from trap cleanup on failure, and excludes it from packaged artifacts.

## Remaining-tasks closure update - 2026-08-04

- Admin moved `ConfigProvider`, Vietnamese locale and static message holder out of `main.jsx` into `AdminThemeProvider`, imported only by lazy `Login` and `AdminLayout` owners. Fresh `build:analyze` passes with budget warnings and measures initial JS at `1388.5 KB`, so this is a valid source cleanup but not a meaningful bundle-size closure.
- `release-all.sh` now writes a structured summary containing each runtime gate and the exact pre-release Git hashes.
- `finalize-release-evidence.php` only promotes browser, transactional and DOCX evidence when the release summary is `passed` and all three summary hashes match the current pushed HEAD values. It refuses stale summaries.
- Browser smoke, transactional E2E and DOCX visual render remain pending until `release-all.sh` runs in a release environment with browser credentials and `soffice`.

## Route-local AntD vendor correction

- Phát hiện `manualChunks` vẫn đẩy AntD, `@ant-design/*` và `rc-*` vào nhánh `vendor` chung dù các chunk tên `antd-*` đã bị loại bỏ.
- Cấu hình mới trả `undefined` cho ba nhóm dependency này trước nhánh vendor tổng quát, để Rollup giữ dependency chỉ dùng ở route lazy trong route owner tương ứng.
- Fresh `npm run build:analyze` đo lại initial JS còn `290 KB` thay vì `1388.5 KB`; initial closure đã dưới budget `650 KB`.
- Route closures vẫn vượt budget, lớn nhất hiện khoảng `1409.4 KB`, vì AntD/rc đã chuyển đúng về route owner. Debt tiếp theo là tách các owner nặng như transaction detail, notification và operation control, không gom lại shared vendor.

## Route-owner follow-up — 2026-08-04

The accepted initial-load measurement remains 290 KB JS / 24.1 KB CSS. Route closure measurements from the previous build are now historical and must be rebuilt after these source changes:

- Login no longer imports the full `BaseForm`; `AuthLoginForm` owns only Form, Input and BaseButton.
- Transaction Detail defers payment, document, timeline and admin-action panels.
- Notifications defer the detail drawer until a record is opened.
- Operations Control defers tab owners and operational modals until selected.

Release evidence must not reuse the previous route-closure values. Run `npm run build:analyze` and record fresh route closures before finalizing evidence.

## Admin UI runtime closure from browser evidence

- Fixed the shared `BaseForm` schema grid to use real CSS-grid column ownership instead of AntD row/span width calculations that collapsed fields into narrow vertical columns.
- `BaseFilter` now searches automatically on select/date changes and debounces text input; the search button remains as an explicit fallback.
- Product approval, payment review, wallet-deposit reconciliation, payout decisions and dispute resolution now follow one review pattern: approve requires confirmation, reject opens a BaseForm modal and requires a concrete reason.
- Audit logs and generated documents now use shared list/page/filter owners, while raw marketplace values are mapped through canonical labels.
- Action Center and Marketplace Operations statistics use responsive grids instead of one-card-per-row layouts.
- Demo fixtures now cover wallets/cash-flow, trust/risk/content, marketplace reviews and issued documents for browser verification.
- These changes are source-verified. Browser UI, full seed/runtime tests, lint and production build must be rerun after applying this source.


## Admin base UI consistency follow-up

- BaseForm now maps legacy `span: 4/6/8/12/24` to semantic 12-column widths so small legacy spans cannot collapse labels and controls into unusable narrow columns.
- BaseFilter now owns a responsive CSS Grid with change-driven search; it no longer depends on fixed AntD Row/Col sizing.
- Audit logs and generated documents use the shared BaseListView shell without a fixed action column that forces unnecessary horizontal clipping.
- Audit event/type/entity presentation now uses the canonical marketplace label owner; the MBN customer label owner was synchronized for the same shared values.
- Existing wallet, payout, trust/risk and generated-document demo fixtures remain the canonical browser-test data owners.

## Admin UI runtime verification - 2026-08-04

- Admin format, lint, check-all and build analyze pass after BaseForm/BaseFilter/review-action cleanup.
- Local browser CRUD smoke passed login, Product/Transaction/Customer forms, BaseForm relation fields, document-template create modal and payout lifecycle actions. Document-template edit modal/API version mutation is strict in release mode and needs a fresh seeded fixture for a non-skip browser mutation run.
- The document-template edit modal smoke may skip only in manual mode; release mode now requires a fresh seeded issued template and fails if the immutable-version mutation does not run.
- Fresh build keeps initial JS at `290.5 KB` / CSS `25.6 KB`; route closures remain over budget and must be split by owner, not by shared vendor.

## Fresh-seed document-template browser gate closure

- `release:all` now runs Admin CRUD smoke with `ADMIN_E2E_REQUIRE_DOCUMENT_VERSION_MUTATION=1`.
- A fresh demo seed must expose at least one published template with generated documents.
- The browser gate must open the matching row, create the successor version, and verify `supersedes_template_id`; missing fixture/action/mutation now fails release instead of silently skipping.



## Strict document-template browser gate verification

- API fresh seed fixture contract now passes in the full PHPUnit suite with `116 tests / 1042 assertions`.
- Admin release runner enforces `ADMIN_E2E_REQUIRE_DOCUMENT_VERSION_MUTATION=1`; manual smoke may still skip only outside release mode.
- Browser document-template mutation remains pending until `release:all` runs against a fresh seeded database and promotes the hash-matched release summary.


## Route, visual and dependency closure follow-up

- Admin transaction detail now mounts only the active payment/document/timeline/admin panel.
- MBN theme/application shell is lazy-owned; account and page CSS are loaded by route owners instead of the global entry manifest.
- Axios security floor is raised to 1.19.0 and form-data to 4.0.6; npm audit is a formal release gate.
- Admin and MBN visual regression runners capture desktop/tablet/mobile screenshots and fail on horizontal overflow or compressed form controls.
- Previous bundle measurements remain historical until a fresh build after these source changes.

## Dependency and bundle verification update

- Admin and MBN use `react-router` 8.3.0 directly instead of `react-router-dom`; `npm audit --audit-level=moderate` now reports zero vulnerabilities in both frontend repos.
- API full PHPUnit now passes with `117 tests / 1043 assertions`, including the isolated avatar upload limiter regression.
- Fresh Admin `build:analyze` measures initial closure at `292.0 KB JS / 25.6 KB CSS`; route closures remain over budget and must be handled by owner-level splitting.
- Fresh MBN `build:analyze` measures initial closure at `448.8 KB JS / 269.7 KB CSS`; the CSS payload remains the next frontend debt after route-owned CSS movement.
- Browser runtime was rerun locally on fresh seed: MBN core/visual passed avatar upload, logout and avatar persistence after re-login; Admin strict CRUD passed document-template immutable version mutation; Admin visual passed desktop/tablet/mobile route checks.
- Full `release:all` hash-finalized summary still requires a clean committed and pushed source cycle.
- Evidence fields now distinguish independent browser/DOCX runtime passes from the pending hash-matched `release:all` summary; pending transactional API and payout-modal entries remain pending until that release cycle reruns them.

## Route closure, initial CSS and Sass module follow-up

Source state after the latest optimization:

- Admin Reconciliation is split into demand-loaded Summary and Export workspaces.
- Admin Transaction Detail loads the command center and detail sections as lazy route owners; individual payment, document, timeline and admin panels remain active-panel owners.
- Notification detail keeps BaseDrawer but no longer loads AntD Descriptions, Timeline, Tag or Input presentation modules.
- MBN account/profile/document/mobile table styles moved from the global manifest to AccountRouteShell.
- Home and catalog/detail mobile styles are owned by their respective routes.
- Sass manifest files now use `@forward`; no `.scss` file may reintroduce deprecated Sass `@import`.
- Previous Admin route-closure and MBN initial-CSS measurements are historical. A fresh `build:analyze` is required.
- Browser, transactional E2E, DOCX and release evidence must be regenerated from clean pushed source through `composer release:all`.

## Fresh route/CSS measurement after latest source update

- API release-package guard, maintainability guard, Pint and full PHPUnit pass on the current working tree: `117 tests / 1043 assertions`.
- Admin `check:all` and `build:analyze` pass after the Reconciliation, Transaction Detail and Notification owner split. Fresh initial closure is `293.1 KB JS / 25.6 KB CSS`, under the initial budget.
- Admin route closures remain over budget; the largest measured lazy route is `ReconciliationExportWorkspace.jsx` at `1389.8 KB JS`. This is accepted as route-level debt, not an initial-load blocker.
- MBN ownership, lint and `build:analyze` pass after route-owned CSS and Sass manifest cleanup. Fresh initial closure is `448.8 KB JS / 191.3 KB CSS`; CSS improved from the previous `269.7 KB` but remains the next frontend debt.
- `src/styles/pages/content-route.css` was removed in favor of the Sass owner `content-route.scss`; maintainability now ignores deleted tracked files while the deletion is staged.
- Browser, transactional E2E, DOCX visual render and hash-finalized release summary remain pending until the next clean committed/pushed `release:all` cycle.
