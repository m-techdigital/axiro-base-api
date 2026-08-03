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

## Deferred SLA clock decision

- The current operational SLA summary intentionally continues using `transactions.updated_at` because the canonical lifecycle does not yet own `status_entered_at`, `current_stage_started_at` or `sla_started_at`.
- Replacing the clock without a lifecycle-owned timestamp would create inferred state and an unproven migration contract.
- Introduce a dedicated SLA clock only together with lifecycle mutation coverage; do not add a generic SLA engine in this closure.

## Large-file ownership follow-up — 2026-08-03

- Large files are split only when a stable business owner exists; line count alone is not a reason to introduce another abstraction.
- `TransactionPaymentPlanService` is the canonical owner for rental pricing snapshots and initial purchase/rental payment-plan generation.
- `TransactionLifecycleService` remains the public lifecycle facade and transaction-boundary owner; controllers and callers do not depend on payment-plan internals.
- Payment submission, confirmation, settlement, transition, and dispute ownership remain unchanged in this follow-up.
