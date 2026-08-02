# Admin Notification Detail, Settlement Filters & Bundle Closure

**Ngày:** 2026-08-03

- Admin notification detail là drawer nhẹ, có customer context, transaction context và transaction timeline.
- Rental settlement list/export dùng cùng filter: ngày hoàn tất, khách hàng, trạng thái và transaction.
- Admin routes dùng React lazy + Suspense để tách chunk theo màn hình, không đổi route contract.
- Customer wording giải thích tiền cọc theo hướng đối chiếu, căn cứ khấu trừ và số tiền cọc thực nhận.
- Không mở rộng RBAC/company/project/accounting/SLA/fraud/BI.
- Schema vẫn sửa migration gốc vì baseline đang phát triển; additive migration là điều kiện khi chuyển sang DB cần giữ dữ liệu.
