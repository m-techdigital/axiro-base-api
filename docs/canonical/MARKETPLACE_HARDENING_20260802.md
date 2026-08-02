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

## Diem da kiem tra

- `php artisan test`: pass 78 tests, 733 assertions.
- Da sua transition `held -> sold/rented` vi luong thanh toan hoan tat can chot san pham truc tiep tu trang thai hold.
- Pham vi anh huong tap trung vao marketplace transaction/product availability, khong cham sang cac module bi cam trong AGENTS.

## Dau viec nen bo sung cho admin

1. Man hinh hold monitor: loc san pham dang hold, sap het han, da expire, nguon hold va customer lien quan.
2. Timeline availability trong chi tiet san pham: available, held, transacting, sold, rented, suspended, nguon thay doi va note.
3. Hang doi duplicate checkout/idempotency: hien request hash trung, transaction da tra ve, thoi diem lap lai.
4. Tac vu admin release hold co ly do bat buoc, co audit note va phan quyen sau nay neu can.
5. Dashboard stuck transaction: pending payment qua han, da thanh toan nhung chua complete, tranh chap dang mo.
6. Bang doi soat wallet/payout: before/after balance, release escrow, refund deposit va exception queue.
7. Filter availability/version tren danh sach san pham de admin thay nhanh ban ghi dang bi tranh chap.
8. Checklist tai lieu bang chung theo giao dich: snapshot, payment proof, delivery proof, acceptance/dispute.
9. Canh bao spam hold/checkout nhieu lan trong thoi gian ngan theo buyer/product.
10. Bao cao SLA theo tung buoc nghiep vu: hold, payment, delivery, acceptance, refund/payout.

## Ghi chu rui ro con lai

- Neu can resale/reset san pham da `sold`, nen thiet ke admin override rieng thay vi mo transition dai tra trong base.
- Frontend nen tiep tuc gui `availability_version`; backend co fallback de tuong thich nguoc, nhung strict concurrency tot hon khi client gui ro version.
- Command expire hold can cron/worker production goi scheduler Laravel deu dan.
