# Luồng tài chính Marketplace v31

## Nguồn chuẩn từ AXIRO cha

Bản mini kế thừa các nguyên tắc từ Transaction, ContractPayment, PaymentCollection và bộ tính tài chính của AXIRO cha:

- Một chủ sở hữu canonical cho lịch thanh toán.
- Tiền không dùng số thực dấu phẩy động trong cơ sở dữ liệu.
- Xác nhận thanh toán có tính lặp an toàn.
- Dòng tiền có số dư trước/sau và tham chiếu nghiệp vụ.
- Tiền của giao dịch được tạm giữ trước khi giải ngân.
- Tiền cọc thuê được tách khỏi tiền thuê và hoàn theo vòng đời hoàn trả.

## Ví khách hàng

Ví có hai khoản:

- `available_balance`: số dư khả dụng.
- `held_balance`: số dư đang tạm giữ.

Mọi biến động được ghi vào `wallet_transactions` với:

- available_before / available_after.
- held_before / held_after.
- transaction_id / transaction_payment_id.
- idempotency_key.
- nguồn thanh toán, tham chiếu và thời điểm phát sinh.

## Nạp tiền

Customer tạo `deposit_request`. Admin xác nhận mới phát sinh một bút toán `deposit_confirmed` cộng số dư khả dụng. Yêu cầu nạp và bút toán cộng tiền là hai bản ghi riêng để giữ dấu vết đối soát.

## Thanh toán giao dịch

### Thanh toán bằng ví

1. Trừ số dư khả dụng của người mua/người thuê.
2. Cộng số tiền tương ứng vào số dư tạm giữ của người bán/người cho thuê.
3. Xác nhận kỳ thanh toán ngay trong cùng transaction DB.
4. Ghi hai bút toán đối ứng và liên kết với kỳ thanh toán.

### Thanh toán ngoài ví

1. Customer gửi tham chiếu thanh toán.
2. Admin đối soát.
3. Khi xác nhận, tiền được ghi vào khoản tạm giữ của người bán/người cho thuê.
4. Chỉ giải ngân khi giao dịch hoàn tất.

## Mua bán

- Thanh toán đủ, đặt cọc hoặc trả góp.
- Trả góp có số kỳ và khoảng cách kỳ theo ngày/tuần/tháng.
- Tiền đã xác nhận được giữ cho đến khi người bán bàn giao, người mua nhận và người mua hoàn tất.
- Khi hoàn tất, toàn bộ tiền tạm giữ được giải ngân cho người bán.

## Cho thuê

Tin cho thuê có nhiều `listing_rental_rates`, ví dụ 6 giờ, 1 ngày, 3 ngày, 1 tuần.

Mỗi gói có:

- đơn vị và số lượng kỳ hạn;
- giá thuê;
- tiền cọc riêng;
- trạng thái mặc định/hoạt động.

Giao dịch thuê sinh:

- một kỳ `security_deposit` có thể hoàn;
- một hoặc nhiều kỳ `rental_cycle`;
- period_start / period_end;
- due_date theo cách thu trước toàn kỳ hoặc thu định kỳ.

Khi hoàn tất thuê:

- tiền thuê được giải ngân cho người cho thuê;
- tiền cọc được trừ khỏi khoản tạm giữ của người cho thuê và cộng lại vào số dư khả dụng của người thuê.

## Quá hạn

Lệnh `php artisan marketplace:scan-due`:

- đánh dấu kỳ thanh toán quá hạn;
- đánh dấu giao dịch thuê quá hạn;
- gửi thông báo cho các bên.

Scheduler chạy mỗi giờ.
