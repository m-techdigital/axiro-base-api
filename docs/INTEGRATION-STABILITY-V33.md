# AXIRO Mini API — Integration Stability v33

## Phạm vi

- Sửa dòng tiền ví: chuẩn hóa số tiền rỗng/null về `0.00`.
- Sửa khóa chống ghi lặp khi giải ngân và hoàn tiền: mỗi vế ghi sổ có khóa riêng.
- Sửa đủ trường trộn cho toàn bộ 13 mẫu tài liệu.
- Chuẩn hóa helper response theo cấu trúc AXIRO, kèm request/correlation ID.
- Việt hóa các thông báo API còn dùng tiếng Anh.
- Bổ sung test hồi quy giải ngân, chuyển khoản tạm giữ và khóa idempotency.

## Kiểm tra bắt buộc

```bash
php artisan migrate:fresh --seed
php artisan test --filter=MarketplaceWalletDefaultsRegressionTest
php artisan test --filter=MarketplaceFinanceClosureTest
php artisan test --filter=MarketplaceDocumentTemplateQualityTest
php artisan test --filter=MarketplaceDocumentWorkflowTest
php artisan test --filter=MarketplaceAdminCustomerSyncTest
php artisan test --filter=MarketplaceWorkflowTest
php artisan test
```
