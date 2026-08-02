# AXIRO Mini Admin Operations Usability Closure

Ngày: 2026-08-02

## Phạm vi

- Giữ mô hình một admin, nhiều khách hàng.
- Loại domain hợp đồng nghiệp vụ; tài liệu/hồ sơ chỉ thuộc giao dịch.
- Không port RBAC, company/project/team, fraud engine, SLA engine hoặc BI riêng.

## Parent-first

Đối chiếu AXIRO cha mới nhất tại:

- badge/count pattern trong base/menu;
- transaction detail read model và action ownership;
- checklist pattern;
- Product availability history.

Phân loại:

- `bounded_adapter`: menu counters và transaction admin actions.
- `mini_bounded`: checklist giao dịch bốn bước.
- `excluded`: generic SLA/fraud/reporting engine.

## Thay đổi

### API

- Bỏ model/controller/request/migration Contract nghiệp vụ.
- Bỏ `contract_id` khỏi generated documents.
- Giữ `MarketplaceContract` như hợp đồng tích hợp API công khai, không phải module hợp đồng nghiệp vụ.
- Transaction detail trả `admin_actions` và `workflow_checklist`.
- Operations overview trả counter: hold quá hạn, thanh toán chờ xác nhận, tranh chấp mở.
- Checklist đơn giản: thanh toán, bàn giao, xác nhận, tranh chấp.
- Tổng tiền CRUD admin dùng MoneyMath thay cho float.

### Admin

- Sidebar hiển thị counter nhẹ và tự làm mới mỗi 60 giây.
- Transaction detail chỉ hiện action hợp lệ theo trạng thái.
- Cho xác nhận khoản thanh toán ngay trong chi tiết.
- Hủy giao dịch ghi rõ bao gồm hoàn phần tiền đang tạm giữ.
- Hiển thị checklist xử lý.
- Đổi copy không cần thiết từ “hợp đồng” sang “hồ sơ giao dịch”.

### Customer

- Đổi copy tài liệu sang “hồ sơ giao dịch” và “hồ sơ mua bán/thuê”.

## Kiểm tra

- API PHPUnit: PASS, 80 tests / 757 assertions.
- API whitespace diff check: PASS.
- Admin `npm run check:all`: PASS.
- Admin `npm run build`: PASS.
- Admin whitespace diff check: PASS.
- MBN `npm run lint`: PASS.
- MBN `npm run build`: PASS.
- MBN marketplace/API contract checks: PASS.
- MBN whitespace diff check: PASS.
