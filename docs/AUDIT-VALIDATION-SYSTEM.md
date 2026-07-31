# Nhật ký lịch sử và xác thực dữ liệu

Hệ thống dùng một bảng canonical `audit_logs` để tránh tạo nhiều loại nhật ký chồng chéo.

## Nhóm nhật ký
- `business_trail`: lịch sử tạo, sửa, xóa, khôi phục dữ liệu nghiệp vụ.
- `system_operation`: yêu cầu POST/PUT/PATCH/DELETE và kết quả HTTP.
- `validation`: yêu cầu bị từ chối do dữ liệu không hợp lệ.
- `security`: dành cho sự kiện đăng nhập, khóa tài khoản và bất thường trong các vòng phát triển sau.

## Dấu vết bắt buộc
Mỗi bản ghi có request ID, correlation ID, tác nhân, đối tượng, ngữ cảnh, IP, thiết bị, dữ liệu cũ/mới, trường thay đổi và mức rủi ro.

## Bảo mật
Mật khẩu, token, OTP, cookie, khóa bí mật, mã khôi phục và thông tin thẻ luôn bị che. Payload dài hoặc lồng quá sâu được rút gọn.

## Validation contract
Mọi lỗi 422 trả về:
- `error_code = VALIDATION_FAILED`
- thông báo tiếng Việt dễ hiểu
- `errors` theo từng trường
- `meta.request_id` và `meta.correlation_id`

Frontend phải hiển thị lỗi tại trường, thông báo tổng quát và request ID khi cần hỗ trợ.
