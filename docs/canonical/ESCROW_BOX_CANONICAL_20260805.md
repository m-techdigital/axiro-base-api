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
