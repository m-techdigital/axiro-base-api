# Transaction Document Type and Dispute Closure 2026-08-02

## Quyết định

- Key tài liệu canonical mới: `sale_record`, `rental_record`.
- API vẫn đọc template legacy `sale_contract`, `rental_contract` trong giai đoạn chuyển tiếp nhưng chỉ phát hành document type canonical.
- `Transaction` tiếp tục là lifecycle owner; GeneratedDocument chỉ là hồ sơ/bằng chứng.
- Tranh chấp không được đóng bằng trạng thái giao dịch mơ hồ. Admin phải chọn một outcome rõ: hoàn tất, hủy và hoàn tiền, hủy không hoàn tiền, hoặc từ chối để mở lại luồng xử lý.

## Ranh giới

Không thêm Contract domain, RBAC, company/project/team, fraud engine, SLA engine hoặc BI riêng.

## Tham chiếu AXIRO cha

- Giữ pattern FormRequest làm mutation boundary giống `GenerateDocumentRequest` và `DocumentTemplateRequest`.
- Giữ document type/status là finite values có owner rõ; Mini dùng `DocumentType` để canonical hóa key và alias legacy.
- Giữ transaction status là kết quả của action lifecycle, không mở field trạng thái thô cho admin nhập.
- Không port approval center, document compliance, accounting sync hoặc system operations đầy đủ vì vượt phạm vi một admin nhiều khách hàng.
- Có thể học pattern option/label rõ ở form sinh tài liệu; không để admin nhập ID hoặc key thô khi thao tác hồ sơ.

## Đánh giá phạm vi ảnh hưởng

- API chỉ thay đổi document type, dispute resolution request và transaction lifecycle action; không mở module nghiệp vụ mới.
- Admin chỉ cập nhật label/key hồ sơ và modal xử lý tranh chấp; không thêm workflow nhiều cấp.
- Customer chỉ cần label canonical mới; không nhận thêm operations complexity.
- Dữ liệu cũ dùng `sale_contract` hoặc `rental_contract` vẫn được đọc qua alias trong giai đoạn chuyển tiếp.

## Đầu việc tiếp theo

- Bổ sung migration chuyển dữ liệu cũ từ `sale_contract`/`rental_contract` sang `sale_record`/`rental_record` khi quyết định bỏ alias.
- Thêm API options cho document types để Admin/Customer lấy label từ backend thay vì giữ mapping cục bộ.
- Chuẩn hóa outcome tranh chấp ra enum/constant dùng chung giữa request, service, test và frontend.
- Bổ sung action log/notification sau mỗi outcome tranh chấp để hai khách hàng nhìn thấy kết quả xử lý.
- Rà end-to-end checkout -> payment -> handover -> acceptance -> completion/cancel/refund với test tích hợp một đường xanh và một đường tranh chấp.

## Gate bắt buộc

- `php artisan migrate:fresh --seed`
- `php artisan test --filter=AdminTransactionDetailActionsTest`
- `php artisan test --filter=MarketplaceDocumentWorkflowTest`
- `php artisan test`
- Admin/MBN format, check, lint và build.
