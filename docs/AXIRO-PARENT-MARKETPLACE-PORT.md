# Ánh xạ AXIRO cha sang AXIRO Mini Marketplace

## Nguồn đã đối chiếu

- `app/Models/Customer.php`, `CustomerService.php`, `CustomerPortalService.php`
- `Product.php`, `ProductInventoryService.php`, `ProductHold.php`
- `Reservation.php`, `ReservationService.php`
- `Transaction.php`, `TransactionService.php`
- `Contract.php`, `ContractLifecycleEvent.php`, `ContractService.php`
- `ContractPayment.php`, `ContractPaymentService.php`, `PaymentCollectionService.php`
- `CustomerConflictService.php`, `CustomerRiskService.php`

## Phần được port vào Mini

| AXIRO cha | AXIRO Mini canonical | Mục đích MBN |
|---|---|---|
| Customer | Customer + Customer Auth | Tài khoản người mua/bán/thuê/cho thuê |
| Product | Product game account | Tài sản gốc, không trộn với tin đăng |
| ProductHold / Reservation | ProductListing trạng thái reserved | Giữ chỗ khi bắt đầu giao dịch |
| Transaction | Transaction purchase/rental | Một giao dịch chung cho hai phía |
| Contract lifecycle | Contract + TransactionEvent | Thỏa thuận và nhật ký vòng đời |
| ContractPayment / PaymentCollection | TransactionPayment | Thanh toán đủ, đặt cọc, trả góp |
| Customer balance concepts | CustomerWallet + WalletTransaction | Nạp tiền và đối soát số dư |
| Customer conflict/risk | MarketplaceDispute | Tranh chấp có bằng chứng và kết luận |

## Phần chủ đích không port

- RBAC, company, department, project scope.
- Accounting journal, commission, payroll.
- Approval nhiều tầng.
- Contract invoice/cost item chuyên sâu.
- Customer segmentation, campaign, consent nâng cao.

## Vòng đời canonical

### Tin đăng

`draft → pending_review → published → reserved → completed`

Nhánh lỗi: `pending_review → rejected`, `published → cancelled`.

### Giao dịch mua

`pending_payment → partially_paid/paid → handed_over → completed`

### Giao dịch thuê

`pending_payment → paid → active → returned → completed`

Mọi giao dịch có thể chuyển sang `disputed` khi một bên mở tranh chấp.
