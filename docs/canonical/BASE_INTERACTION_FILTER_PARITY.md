# Base interaction, filter và action parity

AXIRO Mini Admin lấy AXIRO cha làm chuẩn foundation nhưng giữ phạm vi một admin vận hành nhiều customer.

## Owner bắt buộc

- `BaseModal`: nút đóng, Escape, footer action, khoảng cách nội dung.
- `BaseFilter`: field schema, placeholder, search/reset, URL query state; mặc định không lặp title/label phía trên control.
- `BaseListView`: page header, filter surface và table surface.
- `BaseIconAction`: action nhỏ trong bảng dùng icon + tooltip + aria-label.
- `ListQueryRequest` và `AppliesListQuery` ở API: filter/sort/pagination allowlist.

## Quy tắc

1. List page không tự dựng `Card + Input.Search + Table`.
2. Action cột bảng ưu tiên icon; label chỉ dùng cho primary page action hoặc action cần giải thích dài.
3. Modal phải có nút đóng rõ, Escape và nút Hủy/Lưu theo cùng thứ tự.
4. Filter không đặt heading/title lặp lại tên field; label chỉ bật khi form nghiệp vụ cần giải thích.
5. Mọi filter hiển thị ở Admin phải có filter tương ứng được allowlist và áp dụng ở API.
6. Sort chỉ chấp nhận cột allowlist; `per_page` tối đa 100.
