# AXIRO Mini / MBN — Audit, History & Validation v30

## 1. Kết quả kiểm tra trước khi xử lý

Bản v29 đã có `transaction_events` để thể hiện tiến trình giao dịch, nhưng chưa đủ để làm nhật ký hệ thống và điều tra sự cố:

- Chưa ghi lịch sử tạo/sửa/xóa của Customer, Product, Listing, Payment, Contract, Dispute, Document.
- Chưa có dữ liệu cũ/mới và danh sách trường thay đổi.
- Chưa có request ID/correlation ID xuyên suốt API, Admin và MBN.
- Lỗi 422 chưa có contract thống nhất và chưa được lưu lịch sử.
- Chưa che password, token, OTP, cookie, khóa bí mật trong payload log.
- Chưa có mức độ rủi ro, thống kê, bộ lọc và trang tra cứu tập trung.
- Chưa có retention/prune nên log có nguy cơ tăng vô hạn.
- MBN và Admin chưa hiển thị cùng một lịch sử kiểm tra của giao dịch.

## 2. Thành phần được chuyển từ AXIRO cha

Đã đối chiếu và rút gọn các pattern phù hợp từ:

- `Activity` / `ActivityService`.
- `SystemOperationLog` / `LogSystemOperation`.
- `BusinessAuditTrailService`.
- `AuditPayloadSanitizer`.
- Request ID và correlation persistence.
- Localized validation contract.
- Audit retention policy.

Mini base không copy toàn bộ Audit Center, company/project scope hoặc RBAC. Thay vào đó dùng một bảng canonical `audit_logs` để tránh hai hoặc ba hệ nhật ký song song.

## 3. Backend v30

### Bảng `audit_logs`

Lưu:

- Nhóm nhật ký: business trail, system operation, validation, security.
- Sự kiện: created, updated, deleted, restored, http_mutation, validation_failed.
- Tác nhân: admin, customer, system, guest.
- Đối tượng và ngữ cảnh.
- Request ID và correlation ID.
- Route, method, status code, thời gian xử lý.
- Dữ liệu cũ/mới và trường thay đổi.
- Lỗi validation theo từng trường.
- IP, user-agent và metadata đã làm sạch.
- Mức độ normal/warning/high/critical.

### Model audit tự động

Đã áp dụng cho:

- Customer.
- Product.
- ProductListing.
- Transaction.
- TransactionPayment.
- TransactionCheckpoint.
- Contract.
- MarketplaceDispute.
- WalletTransaction.
- DocumentTemplate.
- GeneratedDocument.
- DocumentAcceptance.

### Validation contract

Mọi lỗi 422 trả về:

```json
{
  "status": {
    "success": false,
    "code": 422,
    "message": "Dữ liệu chưa hợp lệ. Vui lòng kiểm tra các trường được đánh dấu."
  },
  "error_code": "VALIDATION_FAILED",
  "errors": {
    "field": ["Thông báo tiếng Việt cụ thể"]
  },
  "meta": {
    "request_id": "...",
    "correlation_id": "..."
  }
}
```

Lỗi validation được lưu vào `audit_logs`, nhưng password, token, OTP, cookie, thông tin thẻ và khóa bí mật luôn bị che.

### Retention

Cấu hình:

```env
AUDIT_RETENTION_DAYS=365
AUDIT_VALIDATION_RETENTION_DAYS=90
```

Lệnh:

```bash
php artisan audit:prune --dry-run
php artisan audit:prune
```

Scheduler chạy mỗi ngày lúc 02:30.

### API Admin

```text
GET /api/v1/audit-logs
GET /api/v1/audit-logs/statistics
GET /api/v1/audit-logs/{auditLog}
```

Chi tiết giao dịch của Admin và Customer đều trả thêm `audit_history`.

## 4. AXIRO Admin v30

Đã thêm menu **Nhật ký hệ thống**:

- Thống kê tổng, hôm nay, validation failures và rủi ro cao.
- Tìm kiếm theo nội dung/đường dẫn.
- Lọc theo nhóm và mức độ.
- Xem actor, entity, request ID.
- Drawer chi tiết dữ liệu cũ/mới, trường thay đổi, validation errors và metadata.
- Sao chép request ID để tra cứu hỗ trợ.

Trang chi tiết giao dịch có thêm **Lịch sử kiểm tra hệ thống**, hiển thị chung với tiến trình nghiệp vụ.

## 5. MBN v30

Trang chi tiết giao dịch có thêm:

- Lịch sử kiểm tra hệ thống.
- Tác nhân và thời gian.
- Mức rủi ro.
- Request ID rút gọn.

Khi API trả lỗi:

- Ưu tiên thông báo validation cụ thể đầu tiên.
- Hiển thị thông báo tiếng Việt.
- Kèm mã hỗ trợ/request ID để khách hàng gửi cho quản trị viên.

## 6. Kiểm thử bổ sung

`AuditAndValidationContractTest` kiểm tra:

- Response 422 có `error_code`, field errors, request ID và correlation ID.
- Validation failure được lưu audit.
- Password trong payload bị che.
- Tạo Product sinh business trail.
- Yêu cầu POST sinh system operation log.

## 7. Kiểm tra đã chạy

Đã pass:

- PHP syntax lint toàn bộ Backend: 114+ file.
- Route metadata AXIRO Admin.
- Route contract MBN.
- Kiểm tra thuật ngữ tiếng Việt.
- Scan icon không tồn tại.

Chưa thể chạy Composer test và build frontend trong môi trường đóng gói do thiếu Composer/dependency registry. Cần chạy đầy đủ trên máy local bằng hướng dẫn kèm theo.
