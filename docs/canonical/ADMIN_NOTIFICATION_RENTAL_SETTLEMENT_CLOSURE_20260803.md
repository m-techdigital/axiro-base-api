# Admin Notification, Rental Queue & Settlement Audit Closure

**Ngày:** 2026-08-03

- Admin notification center lọc theo transaction, customer, type và read state.
- Operations queue bổ sung rental overdue, pending return và deposit deduction review.
- Rental settlement audit có list API và CSV export.
- Customer transaction detail giải thích tiền cọc hoàn/khấu trừ.
- Contract snapshot phải ghi nhận `transaction.overdue` và `settlement.partially_refunded` vì đây là trạng thái runtime thật của rental lifecycle.
- Giữ mô hình một admin - nhiều khách hàng; không thêm RBAC, company/project/team, SLA/fraud/BI engine.
