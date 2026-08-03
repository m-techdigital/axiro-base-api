# Baseline Release Checklist

## Baseline được chấp nhận

- Marketplace contract: `2026-08-04.1`
- Admin baseline: `e3e1c2d`
- API baseline: `0f118e4`
- MBN baseline: `e64ce96`
- Parent relationship: `mini_bounded`; không tuyên bố exact parity với AXIRO cha.

## Gate bắt buộc trước merge/tag

1. Generated marketplace contract phải đồng bộ với contract nguồn.
2. Source ownership, semantic owner và maintainability guards phải pass.
3. Admin/MBN phải pass lint và production build; API phải pass Pint và full PHPUnit.
4. Chạy browser smoke MBN với avatar fixture; phải pass upload, reload và logout/login lại.
5. Chạy Admin CRUD browser smoke cho product, transaction, customer, payout và document template.
6. Chạy transactional E2E trên database test đã `migrate:fresh --seed`; không chạy trên production.
7. Chạy bundle analysis và ghi nhận asset vượt budget trước khi tối ưu tiếp.
8. ZIP không được chứa `.env*` ngoài template cho phép, `.git`, `node_modules`, `vendor`, `dist`, `build` hoặc folder bọc ngoài.
9. ZIP phải pass `unzip -tq` hoặc `zip -T`.
10. DOCX phải pass structural integrity và render visual QA bằng LibreOffice.
11. Nếu browser hoặc DOCX visual QA bị bỏ qua, phải ghi waiver rõ trong changelog/release note.

## Dữ liệu demo ổn định

Sau `php artisan migrate:fresh --seed`, các fixture chính:

- Khách mua: `customer` / mật khẩu từ `DEMO_CUSTOMER_PASSWORD` (mặc định local: `change-me`).
- Người bán: `seller`; người thuê: `renter`; người cho thuê: `lessor`.
- Bán thẳng: `NSO-0102`.
- Thuê: `NSO-0201`.
- Trả góp: `NRO-0301`.
- Giao dịch mẫu: `TRX-DEMO-*`.
- Payout mẫu: idempotency key `demo-withdrawal-submitted` và `demo-withdrawal-paid`.

## Browser smoke

Browser smoke kiểm tra login/logout, profile, avatar persistence, product form, purchase list/detail, wallet, payout, document và action đúng theo offer mode.

## Transactional E2E

Transactional E2E xác nhận mutation thật qua API cho mua bằng ví, thuê và đối soát cọc, kỳ trả góp, dispute, payout và document generation. Bắt buộc database test riêng và `MBN_E2E_ALLOW_MUTATION=1`.

## Bằng chứng baseline 2026-08-04

Các kết quả dưới đây thuộc baseline đầu vào; mọi thay đổi sau đó phải chạy lại bằng release gate một lệnh trước merge/tag.

- API full PHPUnit, Pint, marketplace integrity và demo fixture contract đã pass.
- Transactional API E2E đã pass trên database test sau `migrate:fresh --seed`.
- Browser core smoke của MBN đã pass login, account pages, offer-mode actions, payment modal và logout.
- Admin/MBN build, lint, base-first/source ownership và release package guards đã pass.
- Baseline trước từng có `antd-vendor` khoảng 1.295 KB; cấu hình hiện đã tách `antd-core`, `antd-icons` và `antd-rc`. Phải dùng `build:analyze` để xác nhận kích thước thực trước merge.

## Một lệnh release

Chạy từ API repo với `APP_ENV=testing AXIRO_RELEASE_ALLOW_RESET=1 MBN_E2E_LOGIN=... MBN_E2E_PASSWORD=... composer release:all`.
