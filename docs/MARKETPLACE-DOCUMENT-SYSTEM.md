# Hệ tài liệu Marketplace

Hệ tài liệu được rút gọn từ DocumentTemplate và GeneratedDocument của AXIRO cha.

## Nguồn chuẩn

- `document_templates`: mẫu HTML, loại tài liệu, phiên bản và trường trộn.
- `generated_documents`: bản tài liệu đã phát hành theo từng giao dịch.
- `document_acceptances`: xác nhận điện tử riêng của người mua/thuê và người bán/cho thuê.

## Loại tài liệu

- Hợp đồng mua bán tài khoản trò chơi.
- Hợp đồng thuê tài khoản trò chơi.
- Phụ lục lịch trả góp.
- Xác nhận đặt cọc.
- Xác nhận thanh toán.
- Biên bản bàn giao.
- Biên bản hoàn trả tài khoản thuê.
- Biên bản tiếp nhận tranh chấp.

Tài liệu được sinh theo trạng thái giao dịch. Bản đã phát hành giữ nguyên nội dung; khi cần cập nhật phải tạo phiên bản mới.
