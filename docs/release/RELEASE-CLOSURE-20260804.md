# Release Closure 2026-08-04

## Contract và scope

- Marketplace contract: `2026-08-04.1`.
- Mô hình: một quản trị viên vận hành nhiều khách hàng.
- Runtime chỉ giữ customer, product, transaction, payment, wallet, payout, document, support và trust.
- Không thêm RBAC phân tầng, company, department, employee, HR, accounting, project hoặc report runtime từ AXIRO cha.

## Đầu việc đã đóng

- Avatar E2E có ảnh fixture chuẩn, kiểm tra sau upload, reload và logout/login lại.
- Admin có browser CRUD smoke cho BaseForm product, transaction, customer, payout surface và document template; payout action lifecycle được kiểm chứng bằng modal xử lý yêu cầu.
- Document template dùng vòng đời `draft → published → deprecated`; mẫu đã dùng tạo phiên bản mới, tài liệu lịch sử không đổi owner template.
- Admin hiển thị version, trạng thái và số tài liệu đã dùng.
- Luồng thuê dùng cùng cách tính tiền thuê, tiền cọc, số cần trả ban đầu, khấu trừ và số hoàn lại ở Admin/MBN.
- Thông báo lỗi mua/thuê/trả góp/payout được chuyển thành thông điệp nghiệp vụ rõ hơn.
- Admin action center có payment, rental deposit, dispute, payout và product hold queues.
- Admin vendor chunks tách `antd-core`, `antd-icons`, `antd-rc`; chỉ tối ưu tiếp khi bundle analyzer chứng minh lợi ích.
- MBN tách tiếp motion/experience CSS thành owner theo domain, giữ manifest import-only.
- `scripts/release-all.sh` chạy release theo một thứ tự cố định và chỉ reset database khi có safety flag.

## Gate runtime

Browser và transactional E2E chỉ chạy trên database test. Release runner bắt buộc `APP_ENV=testing`, `AXIRO_RELEASE_ALLOW_RESET=1`, tài khoản E2E và mutation safety switch.
