# Escrow Box Canonical — 2026-08-05

## Phạm vi

Escrow Box là module giao dịch trung gian riêng của AXIRO Mini. Box không phải tin đăng Marketplace, không yêu cầu biết username đối tác và không công khai danh tính hai bên.

## Ownership

- `escrow_boxes`: aggregate root cho lời mời, điều khoản, phí, review, handover và inspection.
- `EscrowBoxService`: mutation owner và transaction boundary.
- `EscrowBoxPresenter`: customer-safe alias `Bên A/B`; Admin presenter mới được trả định danh thật.
- `EscrowBoxFeeService`: chọn rule, tính phí mặc định 50.000đ + 10% tiền phát sinh và snapshot khi duyệt.
- `EscrowBoxMediaService`: private disk, re-encode bỏ EXIF, max edge 1920, thumbnail 480, checksum dedupe.
- Payment/wallet/settlement/dispute/document/payout hiện có là downstream owners; không tạo owner tài chính song song.

## Luồng canonical

1. Bên A tạo box và nhận raw invite token đúng một lần; DB chỉ lưu SHA-256.
2. Người đăng nhập có link xem điều khoản dưới bí danh và claim vai trò Bên B.
3. Claim chạy dưới row lock, xóa token hash; người thứ ba nhận thông báo chung “không còn khả dụng”.
4. Mỗi lần sửa điều khoản tăng `agreement_version` và reset xác nhận cả hai bên.
5. Hai bên phải xác nhận cùng một version để chuyển `admin_review`.
6. Admin thẩm định danh tính nội bộ, risk, phí, thứ tự bàn giao; có thể request changes/reject/approve.
7. Chỉ sau approve mới tạo transaction adapter và payment obligations cho tiền bù/phí.
8. Khi mọi obligation đã paid, hệ thống mở handover theo `party_a_first`, `party_b_first` hoặc `simultaneous_admin_observed`.
9. Mỗi bên upload ảnh private và submit checkpoint; Admin verify hoặc yêu cầu bổ sung.
10. Khi hai checkpoint verified, mở inspection. Hai bên cùng xác nhận nhận đúng tài sản mới settlement.
11. Tranh chấp khóa settlement và retention-lock media; Admin dùng dispute owner hiện có để giải quyết.

## Privacy

Customer API không trả customer ID, username, email, phone, avatar hoặc payout details của đối tác. Nội dung điều khoản bị chặn email, số điện thoại, URL và từ khóa kênh liên hệ trực tiếp. Admin API giữ định danh thật để thẩm định.

## Fee

- Default fallback: base fee `50,000.00`, percentage `10%` trên `topup_amount`.
- Fee payer: Bên A, Bên B hoặc chia đều.
- Admin override phải có lý do và audit metadata.
- Rule/version/final fee được snapshot; thay đổi bảng phí không làm đổi box đã duyệt.

## Media

- Input tối đa 10MB/ảnh, 10 ảnh/lần, 20 ảnh/bên/box.
- Re-encode ảnh loại metadata EXIF/GPS, resize, WebP nếu GD hỗ trợ, fallback JPEG.
- Lưu private disk; chỉ stream qua endpoint đã authorize party/Admin.
- Không giữ raw original sau conversion.

## Exclusions

Không thêm company, department, HR, accounting, report hub, project runtime, complex RBAC hoặc plaintext credential storage.

## Admin-created Box with two assigned customers — 2026-08-06

Admin may create a private Escrow Box and assign two existing active customers as Bên A and Bên B. The box starts in `awaiting_party_acceptance`. The API returns two different opaque links, one bound to each assigned customer. A logged-in customer who is not the assigned recipient receives the same generic unavailable response and cannot preview terms.

Each accepted link records the assigned side against the current agreement version and invalidates only that side token. The box advances to `admin_review` only after both assigned customers accept the same version. Admin may rotate links only for sides that have not accepted; rotated links invalidate previous hashes and raw tokens are returned once. Customer resources continue to expose only Bên A/B aliases.

## Validation and replaceable phone invitation

- Customer create and terms-edit screens must use the shared BaseForm state/model and map 422 errors to exact nested fields.
- `exchange` must omit top-up fields. `exchange_with_topup` requires a payer and a top-up of at least 1,000 VND.
- A creator may invite B by a voluntarily supplied registered phone number. The lookup must be database-bounded and row-locked; customer identity remains hidden in customer resources.
- The creator may cancel and replace a phone invite only before acceptance. Cancellation returns the Box to `awaiting_counterparty`, invalidates the prior invitation, and sends the removed invitee a neutral notification.
- Once B accepts, replacing a participant is forbidden; cancellation or Admin resolution must follow the canonical lifecycle.

## UI regression invariants

- Customer-facing copy không mô tả chi tiết implementation validation; chỉ nêu hành động và kết quả người dùng cần thực hiện.
- Chi tiết Box phải dùng owner spacing (`PageStack`) giữa các `PageSection`; không render card trực tiếp liên tiếp dưới `PageShell`.
- Hủy/clone Box là lifecycle actions độc lập, luôn `type=button`, nằm ngoài form và trong action bar căn phải.
- Admin hiển thị agreement history và Box events bằng Timeline owner theo pattern AXIRO Cha, kèm actor, thời gian và snapshot điều khoản mở rộng.
- Guard MBN/Admin phải fail nếu các invariant trên bị phá vỡ.

## Activity and timeline ownership

Escrow Box follows the AXIRO Cha activity contract. Domain mutations write `EscrowBoxEvent`; `EscrowBoxTimelineService` is the bounded adapter that produces the canonical activity/timeline resource shape. Admin must render this data through shared `BaseTimeline` and a module timeline schema. Customer-facing history consumes canonical descriptions and masked actors. A module-specific table or a second Timeline renderer is forbidden.

## Activity Timeline theo AXIRO Cha

- Domain store của Mini vẫn là `EscrowBoxEvent`; đây là bounded adapter, không tạo Activity Inbox/Audit Center của AXIRO Cha.
- Output bắt buộc theo parent activity keys: `activity_type`, `activity_subtype`, `old`, `new`, `changed_by`, `created_at`, đồng thời giữ resource keys `event`, `description`, `actor`, `subject`, `module`, `metadata`, `occurred_at`.
- `useTimeline` là exact source từ AXIRO Cha. Renderer/CSS Mini là adapter mỏng vì Mini không dùng Tailwind và không có company/RBAC.
- Filter và pagination phải chạy trong query DB trước presentation. Customer audience phải che actor và từ chối người ngoài bằng 404.
- Mọi module Mini cần timeline phải dùng `BaseTimeline` + domain schema; không tự dựng renderer/event label map riêng.

