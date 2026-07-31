# Luồng nạp tiền v35

1. Khách hàng tạo yêu cầu nạp tiền.
2. Backend sinh mã yêu cầu, nội dung chuyển khoản và đường dẫn VietQR.
3. Khách hàng chuyển khoản và tải ảnh biên nhận.
4. Yêu cầu chuyển sang `submitted`.
5. Quản trị viên đối soát chứng từ và tiền về.
6. Khi xác nhận, `WalletLedgerService` cộng số dư và lưu số dư trước/sau.

Thông tin QR dùng Quick Link VietQR, cấu hình qua `.env`.
