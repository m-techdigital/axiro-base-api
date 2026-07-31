# Kịch bản dữ liệu mẫu MBN Marketplace

Seeder `MarketplaceDemoSeeder` được xây dựng theo cùng tinh thần với demo data canonical của AXIRO cha: dữ liệu có mã ổn định, chạy lại không tạo trùng, bao phủ nhiều trạng thái và có thể kiểm tra đồng thời trên MBN và AXIRO Admin.

## Tài khoản

| Vai trò | Tên đăng nhập | Mật khẩu | Mục đích |
|---|---|---|---|
| Quản trị | `admin` | `change-me` | Duyệt tin, xác nhận thanh toán, nạp tiền, xử lý tranh chấp |
| Người mua | `customer` | `change-me` | Mua thẳng, trả góp, xem giao dịch đã hoàn tất |
| Người bán | `seller` | `change-me` | Xem tin chờ duyệt, tin bị từ chối, giao dịch đã bán |
| Người thuê | `renter` | `change-me` | Xem giao dịch thuê đang hoạt động |
| Người cho thuê | `lessor` | `change-me` | Xem giao dịch bàn giao và hoàn trả |
| Khách khiếu nại | `dispute` | `change-me` | Xem giao dịch đang tranh chấp |

## Kịch bản chính

| Mã | Kịch bản | Trạng thái |
|---|---|---|
| `TRX-DEMO-INSTALLMENT` | Mua trả góp 3 kỳ | `partially_paid` |
| `TRX-DEMO-COMPLETED-SALE` | Mua bán hoàn tất | `completed` |
| `TRX-DEMO-ACTIVE-RENTAL` | Thuê đang hoạt động | `active` |
| `TRX-DEMO-RETURNED-RENTAL` | Đã hoàn trả tài khoản thuê | `returned` |
| `TRX-DEMO-DISPUTE-OPEN` | Tranh chấp đang mở | `disputed` |
| `TRX-DEMO-CANCELLED` | Hủy trước thanh toán | `cancelled` |

## Tin đăng

- 2 tin đang công khai trên MBN.
- 1 tin đang chờ Admin duyệt.
- 1 tin bị từ chối có lý do rõ ràng.
- Các tin `reserved` và `completed` phục vụ giao dịch lịch sử.

## Dữ liệu tài chính

- Thanh toán kỳ đã xác nhận.
- Thanh toán đã gửi chứng từ, chờ Admin xác nhận.
- Thanh toán chưa đến hạn.
- Nạp tiền đã xác nhận.
- Nạp tiền chờ xác nhận.
- Nạp thẻ bị từ chối.

## Tranh chấp

- `DSP-DEMO-OPEN`: đang mở, có bằng chứng mẫu.
- `DSP-DEMO-RESOLVED`: đã giải quyết, có kết luận và người xử lý.

## Chạy lại

```bash
php artisan migrate:fresh --seed
php artisan test --filter=MarketplaceDemoDataTest
```
