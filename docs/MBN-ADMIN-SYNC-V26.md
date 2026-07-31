# Đồng bộ nghiệp vụ MBN và AXIRO Admin v26

## Nguồn chuẩn

Các vòng đời Customer, Product Listing, Transaction, Payment, Contract và Dispute được rút gọn từ AXIRO cha. AXIRO Mini API là chủ sở hữu duy nhất của trạng thái; MBN và Admin chỉ gửi hành động hợp lệ và đọc lại dữ liệu sau khi xử lý.

## Mốc xác nhận hai phía

### Mua bán

1. Quản trị viên xác nhận thanh toán.
2. Người bán xác nhận đã bàn giao (`seller_handover`).
3. Người mua xác nhận đã nhận (`buyer_receive`).
4. Người mua xác nhận hoàn tất (`complete`).

### Thuê

1. Quản trị viên xác nhận thanh toán.
2. Người cho thuê xác nhận bàn giao (`seller_handover`).
3. Người thuê xác nhận đã nhận (`buyer_receive`) và giao dịch chuyển sang đang thuê.
4. Người thuê gửi yêu cầu hoàn trả (`renter_return`).
5. Người cho thuê xác nhận đã nhận lại (`lessor_receive_return`).
6. Người thuê xác nhận hoàn tất (`complete`).

Mỗi mốc được lưu tại `transaction_checkpoints`, đồng thời ghi `transaction_events` và tạo thông báo cho bên còn lại.

## Trung tâm xử lý Admin

`GET /api/v1/action-center` trả về các hàng đợi:

- Tin đăng chờ duyệt.
- Thanh toán đã gửi chứng từ.
- Nạp tiền chờ xác nhận.
- Tranh chấp đang mở.
- Giao dịch chờ xác nhận bàn giao hoặc hoàn trả.

## Thông báo khách hàng

- `GET /api/v1/customer/notifications`
- `POST /api/v1/customer/notifications/{notification}/read`
- `POST /api/v1/customer/notifications/read-all`

Thông báo được tạo khi tin đăng được duyệt/từ chối, giao dịch được tạo, thanh toán được gửi/xác nhận/từ chối, bàn giao/hoàn trả thay đổi, tranh chấp được mở/xử lý hoặc quản trị viên can thiệp.
