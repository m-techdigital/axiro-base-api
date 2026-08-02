# Transaction options, dispute outcome và lifecycle E2E

- `DocumentType` là owner duy nhất cho key/label hồ sơ giao dịch.
- API `GET /api/v1/marketplace/options` cấp document type và dispute outcome cho Admin/Customer.
- `DisputeOutcome` là enum backend duy nhất; frontend đọc option từ API thay vì giữ mapping riêng.
- `marketplace_disputes.outcome` nằm trực tiếp trong migration gốc vì dự án đang phát triển.
- Mỗi kết quả tranh chấp ghi transaction event và gửi notification cho buyer/seller.
- Hai E2E lifecycle được bảo vệ: hoàn tất bình thường và tranh chấp + refund/cancel.
- Legacy `sale_contract`/`rental_contract` chỉ còn read alias trong `DocumentType`, không được seed/phát hành mới.

## Tham chiếu AXIRO cha

- Đồng bộ pattern enum/options từ backend như `DocumentTemplateType::options()` và `GeneratedDocumentStatus::options()`.
- Giữ mutation boundary bằng FormRequest giống `GenerateDocumentRequest` và `DocumentTemplateRequest`.
- Giữ transaction status là kết quả lifecycle action, không mở field trạng thái thô cho admin chỉnh trực tiếp.
- Học pattern form dùng option rõ ràng, không để admin/customer nhập key hoặc ID thô cho nghiệp vụ hồ sơ.
- Không port approval center, document compliance, accounting sync, system operations hoặc import/export recovery vì vượt phạm vi Mini.

## Đầu việc tiếp theo

- Khi base bắt đầu có database dùng thật, tách `marketplace_disputes.outcome` thành migration bổ sung thay vì chỉnh migration gốc.
- Bổ sung endpoint options có cache/version để Admin/Customer invalidate label khi contract thay đổi.
- Đưa dispute outcome vào transaction history/customer timeline rõ hơn, gồm người xử lý và thời điểm.
- Bổ sung E2E cho rental: thuê -> active -> return -> returned -> completed và thuê -> dispute -> cancel/refund.
- Chuẩn hóa fallback label frontend bằng contract-generated artifact để không phải giữ tay ở nhiều app.
