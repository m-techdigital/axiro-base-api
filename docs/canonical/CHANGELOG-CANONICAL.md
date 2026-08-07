## 2026-08-05 — Direct escrow agreement flow

- Added a private escrow invitation flow that does not require a public marketplace listing.
- The initiator selects buyer/seller role, counterparty username, asset terms, amount, delivery method and inspection policy.
- No payment obligation is created until both parties accept the immutable agreement snapshot.
- Accepted agreements reuse canonical payment, handover, dispute, document, settlement and payout owners.


## 2026-08-05 — Digital asset escrow handover

- Added Mini-bounded escrow handover policy for game accounts and in-game items.
- Reused canonical transaction, payment, snapshot, dispute, document and payout owners.
- Browser/release evidence must be regenerated after this source change.


### Escrow document and demo closure

- Marketplace documents use transaction-level immutable handover snapshots for both game accounts and in-game items.
- The handover and safety-check templates include delivery method, inspection duration/deadline, evidence requirement, and non-sensitive confirmation notes.
- Demo and transactional E2E source cover an `in_game_trade` item flow in addition to account rental/sale flows.
- Secret credentials remain explicitly excluded from database fields, document merge data, notes, and browser forms.

## 2026-08-05 — Digital asset escrow source-package closure

- Removed browser evidence directories from Admin/MBN source trees and removed API `public/storage` runtime content from the source package.
- Release-package guards now fail closed on `artifacts`, coverage/build output and API public runtime storage; regression probes confirmed the guards reject these paths before packaging.
- Digital asset escrow source/static guards pass across API, Admin and MBN. Runtime browser, transactional E2E, `release:all` and hash-finalized evidence remain pending until the source is committed and pushed in the three Git repositories.

## 2026-08-05 — Escrow Box private module

- Replaced username-based direct escrow with a dedicated private Escrow Box aggregate.
- Added one-time hashed invite claim, alias-only customer presenter and contact-information validation.
- Added versioned agreement, Admin review, fee rule/snapshot/override, payment obligations and ordered handover checkpoints.
- Added private optimized media pipeline and dispute retention lock.
- Reused canonical transaction payment, wallet, settlement, dispute, document and payout owners.
- Runtime browser/E2E/release evidence is pending after this source change.

## 2026-08-05 184710 style-baseline carry-forward

- The MBN 184710 deterministic stylesheet manifest, desktop/mobile shell owners, and style architecture guards are retained as the active UI baseline.
- Escrow Box routes, hooks, repositories, privacy contract, and media/handover flows are carried forward onto that baseline.
- Escrow Box CSS is registered once in `src/index.css`; route components do not side-effect import CSS.
- API/Admin Escrow Box owners remain `mini_bounded`; no company, RBAC, HR, accounting, report, or project runtime is introduced.
- Previous build/browser/release evidence is stale after this merge and must be regenerated from clean pushed Git HEADs.

### 2026-08-06 — Admin-assigned Escrow Box

- Admin can create a box, select two active customers, and receive two separate customer-bound acceptance links.
- Both parties must accept the same agreement version before Admin review starts.
- Third parties cannot preview or accept a leaked link.
- Admin can rotate links only for parties that have not accepted.
- Runtime lint/build/Pint/PHPUnit/browser/E2E evidence must be regenerated after this source change.


## 2026-08-06 — Audit payload sanitizer boundary fix

- Restored masking for business-sensitive keys such as `phone`, `cccd`, `total_amount`, `transaction_value`, `signed_url`, and provider credentials.
- Replaced substring matching with normalized exact-key and credential-suffix matching so safe keys such as `token_count` and `secretary_name` remain visible.
- Added focused regression coverage for nested credentials, signed URLs, business money fields, and false-positive boundaries.

## 2026-08-06 - Escrow Box validation and invitation lifecycle

- Unified customer create/edit validation through shared BaseForm owners and nested field-error mapping.
- Fixed React hook order in Escrow Box detail.
- Excluded top-up fields for horizontal exchange.
- Hardened phone invite lookup, cancellation, replacement, notification, and row-lock behavior.
- Kept privacy aliases and Mini-bounded financial owners unchanged.
## 2026-08-06 — Escrow Box candidate resolution and update history

- Lời mời Bên B chuyển sang hai bước: tìm chính xác bằng số điện thoại, xem ứng viên đã che thông tin, sau đó chọn ứng viên mới gửi lời mời.
- Customer API không trả customer id; candidate token được mã hóa, gắn với Box/người tạo và hết hạn sau 10 phút.
- MBN detail có nút tải lại và nút Cập nhật Box; nút Hủy trang cập nhật nằm ngoài form và căn phải.
- Admin detail hiển thị lịch sử từng phiên bản điều khoản, lý do, người cập nhật và snapshot chi tiết.
- Contract nâng lên 2026-08-06.4; runtime/browser evidence phải chạy lại sau source change.

## 2026-08-06 — Escrow Box UI regression closure

- Không hiển thị copy kỹ thuật về độ dài/implementation validation trên customer UI; form chỉ hướng dẫn hành động người dùng.
- Detail phải bọc các `PageSection` trong `PageStack`/owner spacing; card không được dính liền do render trực tiếp dưới `PageShell`.
- Nút hủy/clone là lifecycle action độc lập, `type=button`, nằm ngoài mọi form và căn phải trong action bar riêng.
- Admin lịch sử điều khoản và sự kiện dùng Timeline owner theo pattern AXIRO Cha, có actor, thời gian và snapshot mở rộng; không quay lại bảng lịch sử ghép ô.
- Static guards khóa cả bốn regression trên để tránh tái diễn.

## 2026-08-06 — Escrow Box update action placement

- Nút `Cập nhật` được đặt cùng action bar vòng đời ở cuối trang chi tiết, ngay trước `Hủy box`.
- Action dùng `type="button"`, nằm ngoài mọi form và điều hướng tới route cập nhật riêng `/account/escrow-boxes/:id/edit`.
- Không đặt lại action cập nhật trong header để tránh tách rời nhóm thao tác vòng đời và tránh lặp UI.
- Static guard MBN khóa thứ tự `Cập nhật` → `Hủy box` và sự tồn tại của trang cập nhật riêng.

- Escrow Box: nút Cập nhật dùng quyền `can_update` từ API và hiển thị cả khi đang chờ Bên B chấp nhận; cập nhật trước acceptance giữ nguyên trạng thái/lời mời hiện tại.
- Escrow Box update UX closure: action label is now `Cập nhật`; `Hủy` and `Cập nhật` share one right-aligned action row outside the form on desktop, with an intentional two-column mobile layout. Static guards prevent the old `Lưu phiên bản mới` label and accidental line breaks from returning.

## 2026-08-06 — Escrow Box success feedback closure

- Tạo Box thành công phải phát toast xác nhận và chuyển thẳng đến Detail của Box vừa tạo.
- Raw invite path vừa tạo được truyền bằng route state để Detail có thể hiển thị/sao chép trong phiên điều hướng đầu tiên mà không lưu raw token vào database.
- Cập nhật Box thành công phải phát toast xác nhận, refresh form theo response/version mới và giữ nguyên trang cập nhật.
- Lỗi validation/request vẫn giữ tại form và từng field; không điều hướng khi mutation thất bại.
- MBN static guard khóa hai hành vi success trên và cấm khôi phục trang success trung gian sau create hoặc redirect sau update.

## 2026-08-06 — Parent activity timeline alignment

- AXIRO Cha `ActivityResource`/`BaseTimeline` contract is now the shared standard for Escrow Box activity history.
- Mini keeps a bounded domain adapter: `EscrowBoxEvent` remains the domain event store, while `EscrowBoxTimelineService` maps it to the parent activity keys (`activity_type`, `activity_subtype`, `old`, `new`, `changed_by`, `created_at`).
- Admin renders history through shared `BaseTimeline`; module code no longer owns a second AntD Timeline/table implementation.
- Customer output keeps the same event description contract but aliases actors as Bên A/B/Nền tảng and never exposes counterpart identity.

## 2026-08-06 — Parent Activity Timeline runtime closure

- `useTimeline` dùng nguyên source AXIRO Cha; `BaseTimeline`, `TimelineChangeItem` và CSS là bounded adapter do AXIRO Mini không mang Tailwind/company/RBAC runtime.
- Contract `2026-08-06.6` / API `v70` khai báo rõ cả hai endpoint timeline Escrow Box cho Admin và Customer.
- Backend timeline lọc và phân trang tại SQL boundary, chỉ tải actor và agreement version cần cho trang hiện tại; cấm tải toàn bộ event collection rồi phân trang trong PHP.
- Customer timeline tiếp tục che actor thành Bên A/B/Nền tảng và không trả customer id hoặc tên đối tác.
- `terms_updated` trả old/new đã qua `AuditPayloadSanitizer`; filter `activity_type` và `activity_subtype` được khóa bằng test.
- Static/source/package gates đã chạy lại; Pint, PHPUnit, frontend lint/build, browser và release evidence phải chạy lại trên Git HEAD có dependency đầy đủ.

## 2026-08-06 — Source package cache closure

- API source package đã loại `bootstrap/cache/packages.php` và `bootstrap/cache/services.php`; đây là Composer/Laravel generated cache, không phải source.
- `check-release-package.php` và `check-recovery-baseline.php` hiện fail-closed với mọi file trong `bootstrap/cache/**` ngoại trừ `.gitignore`.
- Regression probe đã xác nhận cả hai checker đều fail khi cố tình thêm lại generated cache.

## 2026-08-06 — Runtime dependency, PHPUnit style và BaseForm controlled bridge closure

- Admin `BaseFormControl` bắt buộc chuyển tiếp `value`, `onChange`, `onBlur`, `id` và accessibility props do Ant Design `Form.Item` inject vào control. Việc bọc control nhưng làm rơi các props này khiến người dùng đã nhập dữ liệu nhưng form vẫn báo `required`.
- API là PHPUnit thuần; Pest DSL (`it`, `expect`, `describe`) bị cấm và được khóa bởi `check-phpunit-style.php`.
- MBN có `doctor:dependencies` để phát hiện `node_modules` cài thiếu/hỏng trước khi chạy Vite. Lỗi thiếu `vite/dist/...`, `react-dom/cjs/...` hoặc Ant Design là lỗi dependency installation, không phải route source fallback.
