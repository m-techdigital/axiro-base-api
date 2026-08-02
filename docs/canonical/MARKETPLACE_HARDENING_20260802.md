# Marketplace hardening 2026-08-02

## Pham vi dong bo tu AXIRO cha

- Nguon doi chieu: `mylands-api-develop-20260802-0359.zip` va `mylands-admin-develop-20260802-0359.zip`.
- Chi port cac mau can thiet cho MBN product-only marketplace: decimal money math, khoa dong optimistic availability, idempotency checkout, row-lock lifecycle va expire hold.
- Khong dua vao base nay cac phu thuoc company, project, team, Reservation/CRM ownership chain, Accounting posting, RBAC graph, inventory hay report.

## Ket qua cap nhat API

- `TransactionCreateRequest` chap nhan `idempotency_key` tu payload hoac header, va `availability_version` de tranh checkout tren du lieu cu.
- `Transaction` luu `idempotency_key`, `request_hash`, `availability_version`, `pricing_snapshot` de giam duplicate checkout va giu audit trail.
- `ProductAvailabilityService` dong bo logic row-lock, transition hop le, version assertion, source ownership va auto expire hold.
- `MoneyMath` chuan hoa tinh tien decimal 2 chu so; backend van la nguon tinh tong tien giao dich.
- Them command `marketplace:expire-product-holds` va schedule moi 5 phut.
- Bo cache build sinh ra trong `bootstrap/cache` khoi working tree, chi giu `.gitignore`.

## Ket qua phat trien Operations Dashboard

- Them endpoint admin `operations-dashboard` cho overview, hold monitor, stuck transaction queues, idempotency audit, reconciliation va document checklist.
- Manual release hold yeu cau note bat buoc, expected availability version va chi chap nhan khi product van dang duoc giu boi dung hold/source.
- Cac endpoint list dung `ListQueryRequest` de giu contract filter, pagination va gioi han `per_page` chung cua base.
- Ghi audit log `product_hold_manual_release` khi admin nha hold thu cong.
- Khong publish CRUD `/contracts`; tai lieu/hop dong neu co chi la chung tu phat sinh theo giao dich.

## Diem da kiem tra

- `php artisan test`: pass 80 tests, 751 assertions khi chay voi `APP_KEY` va `JWT_SECRET` test hop le.
- Da sua transition `held -> sold/rented` vi luong thanh toan hoan tat can chot san pham truc tiep tu trang thai hold.
- Pham vi anh huong tap trung vao marketplace transaction/product availability, khong cham sang cac module bi cam trong AGENTS.

## Trang thai dau viec admin

1. Da co: hold monitor, availability timeline, canh bao checkout lap, manual release hold, transaction queue, reconciliation va document checklist.
2. Can phat trien tiep: counter thong bao nhe cho admin ve hold qua han, thanh toan cho xac nhan va tranh chap mo.
3. Can phat trien tiep: action nhanh tu transaction detail de xu ly thanh toan, ban giao, hoan tien va dong tranh chap.
4. Tam khong phat trien: fraud engine, SLA engine, role/policy nhieu cap, report/BI rieng.

## Ghi chu rui ro con lai

- Neu can resale/reset san pham da `sold`, nen thiet ke admin override rieng thay vi mo transition dai tra trong base.
- Frontend nen tiep tuc gui `availability_version`; backend co fallback de tuong thich nguoc, nhung strict concurrency tot hon khi client gui ro version.
- Command expire hold can cron/worker production goi scheduler Laravel deu dan.
