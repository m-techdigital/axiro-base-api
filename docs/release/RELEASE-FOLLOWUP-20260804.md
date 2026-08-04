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

- API full PHPUnit: pass `116 tests / 1033 assertions`.
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
