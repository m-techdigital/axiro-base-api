# Marketplace Options Invalidation, Rental Overdue & Deposit Deduction

**Ngày:** 2026-08-02  
**Phạm vi:** AXIRO Mini, mô hình một admin - nhiều khách hàng.

## Quyết định

- Admin transaction timeline hiển thị `dispute outcome` bằng label từ options catalog.
- Rental lifecycle có hai tình huống E2E bổ sung:
  - thuê quá hạn;
  - hoàn trả và hoàn tất có khấu trừ một phần tiền cọc.
- Khấu trừ cọc chỉ được thực hiện khi Admin hoàn tất giao dịch thuê.
- Số tiền khấu trừ không được vượt tiền cọc và bắt buộc có lý do.
- Phần tiền cọc còn lại được hoàn cho người thuê; phần khấu trừ được giải ngân cho bên cho thuê.
- Product trở lại `available` sau khi giao dịch thuê hoàn tất.
- Fallback options frontend được sinh từ `marketplace-contract.json` bằng script, không duy trì bảng label viết tay thứ hai.
- Cache options được rút ngắn còn tối đa 30 giây khi version contract server khác version đã build ở client.
- Notification giao dịch có `transaction_id` và `transaction_code` dạng cột riêng để Admin có thể lọc theo hồ sơ giao dịch mà không phải đọc JSON payload.
- Logic khấu trừ cọc phân bổ theo phần khấu trừ còn lại, tránh trừ lặp nếu sau này có nhiều khoản refundable.

## Owner canonical

- Backend options: `MarketplaceOptionsCatalog`.
- Document type: `DocumentType`.
- Dispute outcome: `DisputeOutcome`.
- Transaction notification routing: `MarketplaceNotificationService`.
- Frontend generated fallback: `src/generated/marketplaceOptions.js`.
- Sync script: `scripts/sync-marketplace-options.mjs`.
- Rental settlement: `TransactionLifecycleService::settleCompleted()`.

## Migration

Dự án đang phát triển nên các cột sau được thêm trực tiếp vào migration gốc `transactions`:

- `rental_deposit_deduction_amount`;
- `rental_deposit_deduction_note`.

Notification giao dịch cũng bổ sung trực tiếp vào migration gốc `marketplace_notifications`:

- `transaction_id`;
- `transaction_code`.

Khi bắt đầu vận hành trên database cần giữ dữ liệu, phải chuyển thay đổi schema tương lai sang migration bổ sung.

## Đối chiếu AXIRO cha

- Đồng bộ theo hướng harden notification routing/runtime của AXIRO cha: đưa khóa nghiệp vụ cần lọc ra cột thật, giữ payload JSON cho tương thích.
- Không port calendar reminder, workflow automation, compliance hoặc accounting recovery vì các phần đó vượt phạm vi Mini marketplace.
- Mutation boundary vẫn nằm ở FormRequest + lifecycle service; frontend chỉ gửi action hợp lệ, không tự đổi trạng thái thô.

## Kiểm tra merge

- API lifecycle/options tests đã bao phủ: sale complete, dispute refund, rental complete, rental dispute refund, rental overdue notification và rental deposit deduction.
- Admin/customer build lấy options label từ generated fallback, không duy trì enum label thứ hai.
- Phạm vi ảnh hưởng nằm trong marketplace transaction/options/notification, không thêm module hợp đồng riêng.

## Không mở rộng

Không đưa vào Mini:

- RBAC/company/project/team;
- fraud engine;
- SLA engine;
- Accounting/Financial Reconciliation đầy đủ;
- BI/reporting riêng.
