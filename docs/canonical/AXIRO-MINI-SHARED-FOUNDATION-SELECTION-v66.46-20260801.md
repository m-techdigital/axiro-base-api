# AXIRO Mini Shared Foundation Selection v66.46

## Mục tiêu

Tiếp tục phát triển song song với AXIRO cha nhưng chỉ đưa foundation vào Mini khi có consumer thật, dependency closure đầy đủ và test chặn lỗi import/adoption.

## Phần được tích hợp

### BaseView

`BaseView.jsx` được lấy trực tiếp từ AXIRO cha. Trang chi tiết giao dịch là consumer đầu tiên, thay cho khối `Descriptions` viết tay.

Lợi ích:

- schema hiển thị dùng chung;
- span responsive theo field;
- enum/tag/money/date dùng renderer chung;
- giảm mapping thủ công và raw value;
- có thể tái sử dụng cho customer, product, contract và payout dossier ở các lượt sau.

### fieldRenderer

Renderer của AXIRO cha được đưa vào với hai adapter import có giới hạn:

- `@/lib/dayjs` đổi sang package `dayjs` đã khai báo trong Mini;
- `formatMoney` ánh xạ sang `formatCurrency` canonical của Mini.

Các type đã có closure gồm text, date/datetime, option/tag, relation, user, number/money, image, editor output, JSON, network, device và location.

## Phần chưa tích hợp máy móc

- `BaseDynamicFormList`: Mini chưa có consumer production dùng `Form.List`.
- `BaseUpload`/`BaseImageUpload`: thiếu file service và authorization lifecycle tương đương cha.
- `BaseEditor`: thiếu full Tiptap/storage/sanitization closure.
- `BaseTimeline`: thiếu timeline service và activity metadata tương đương.
- `BaseViewPage`/`BaseTabbedListPage`: còn phụ thuộc module registry và RBAC của cha.

## Gate

`npm run check:parent-view-foundation` kiểm tra:

- BaseView và fieldRenderer tồn tại;
- BaseView được export;
- transaction detail đã adoption;
- legacy `Descriptions` không quay lại;
- các renderer type quan trọng còn tồn tại.

Đã chạy pass:

- Admin `npm run check:all`;
- Admin `npm run build`;
- API `php artisan test`;
- `check:source-closure`;
- `check:renderer-closure`;
- `check:parent-view-foundation`.

Admin build vẫn có cảnh báo chunk lớn của Vite; đây là cảnh báo tối ưu bundle, không phải lỗi merge base.
