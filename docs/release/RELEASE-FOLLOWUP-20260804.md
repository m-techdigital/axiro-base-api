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

- API full PHPUnit: pass `116 tests / 1029 assertions`.
- API Pint và maintainability guard: pass.
- Admin `check:all`: pass.
- Admin `build:analyze`: pass, có cảnh báo budget thật `initial JS 1463.9 KB`, largest asset `antd-rc 658.5 KB`.
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
