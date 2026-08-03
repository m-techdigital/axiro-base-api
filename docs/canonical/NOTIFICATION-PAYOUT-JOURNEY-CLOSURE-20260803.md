# Notification Deep-Link, Payout Journey & Mobile Action Polish

**Ngày:** 2026-08-03  
**Phạm vi:** AXIRO Mini, mô hình một admin - nhiều khách hàng.

## Quyết định canonical

- Notification detail phải trả `action_context` gồm deep-link, next action và blocking reasons.
- Notification chỉ dẫn Admin tới đúng hồ sơ cần xử lý; không chỉ là danh sách đọc/chưa đọc.
- Payout/verification journey phải có một shape backend-owned:
  - các bước;
  - next action;
  - điều kiện bị khóa;
  - context số dư và tài khoản nhận tiền.
- Transaction action reason phải nêu rõ khoản thanh toán quá hạn hoặc tài liệu còn thiếu khi có thể.
- Customer payout UI phải mobile-first, CTA tối thiểu 48px và lý do bị khóa dễ đọc.
- Không đưa RBAC, company/project/team, generic workflow, SLA engine, fraud engine hoặc Accounting vào Mini.

## Owner

- Transaction next action: `TransactionLifecycleCatalog`.
- Notification action context: `AdminNotificationController`.
- Payout journey: `PayoutJourneyPresenter`.
- Admin notification detail: `notifications/pages/List.jsx`.
- Admin payout command context: `payouts/pages/Index.jsx`.
- Customer payout journey: `PayoutPage.jsx`.

## Trạng thái kiểm tra

- PHP syntax toàn API: pass.
- Admin source checks: pass.
- MBN source checks: pass.
- Runtime PHPUnit/lint/build/browser smoke: chạy tại máy phát triển có dependency.
