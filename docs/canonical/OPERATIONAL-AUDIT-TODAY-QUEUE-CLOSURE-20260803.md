# AXIRO Mini - Operational Audit & Today Queue Closure

**Ngày:** 2026-08-03  
**Phạm vi:** một admin - nhiều khách hàng.

## Quyết định

- Notification có trạng thái `handled` riêng với read/unread.
- Action hoàn tất giao dịch hoặc giải quyết tranh chấp tự đóng notification liên quan.
- Payout, seller verification và payout account review phải ghi audit actor/time/reason.
- Dashboard Today gom payout chờ xử lý, giao dịch kẹt, tài liệu chờ xác nhận và notification chưa xử lý.
- Timeline vận hành dùng chung shape cho transaction, withdrawal và support case.
- Customer payout journey hiển thị rõ trạng thái đang chờ xác minh hoặc đang chờ chi trả.
- Code đọc vận hành phải tách owner:
  - `MarketplaceOperationsReadService` giữ query/list/read model.
  - `OperationalWorkPresenter` giữ today queue, checklist và SLA summary.
  - `OperationalTimelinePresenter` giữ timeline shape.

## Không mở rộng

Không port RBAC, company/project/team, Accounting, Reports, generic workflow/SLA hoặc fraud engine.
