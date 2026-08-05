# Giao dịch trung gian tài sản số

## Phạm vi Mini bounded

Chức năng mở rộng transaction lifecycle hiện có cho hai loại sản phẩm `game_account` và `item`. Không tạo module escrow song song, không thêm company, RBAC, accounting hoặc report runtime.

## Ownership

- Product cấu hình phương thức bàn giao, thời gian kiểm tra và yêu cầu biên bản trước bàn giao.
- Transaction chụp bất biến cấu hình bàn giao tại thời điểm checkout.
- `TransactionEscrowHandoverService` sở hữu điều kiện bàn giao và bắt đầu cửa sổ kiểm tra.
- `TransactionLifecycleService` tiếp tục là facade/transaction coordinator.
- Admin dùng `BaseForm` và transaction detail owners hiện có.
- MBN dùng `useProductForm`, repository canonical, `useTransactionEscrowAction`, `GamingModal` và `BaseForm`.

## Luồng

1. Người bán đăng nick hoặc vật phẩm, chọn phương thức bàn giao và thời gian kiểm tra.
2. Checkout chụp cấu hình này vào transaction; thay đổi sản phẩm sau đó không làm đổi giao dịch đang chạy.
3. Nếu sản phẩm yêu cầu biên bản, người bán phải lưu ảnh `before_handover` trước khi xác nhận bàn giao.
4. Xác nhận bàn giao tạo checkpoint, ghi hướng dẫn không chứa bí mật và bắt đầu `inspection_deadline_at`.
5. Người mua xác nhận đã nhận/kiểm tra; tiền vẫn được giải ngân theo settlement lifecycle canonical khi giao dịch hoàn tất.
6. Khi có vấn đề, hai bên dùng dispute/case và evidence hiện có; không tạo luồng khiếu nại thứ hai.

## An toàn

Không lưu mật khẩu, OTP hoặc mã khôi phục trong note. Note chỉ chứa hướng dẫn kênh bàn giao và kết quả kiểm tra. Thông tin nhạy cảm phải đi qua kênh bảo mật do nền tảng vận hành.
