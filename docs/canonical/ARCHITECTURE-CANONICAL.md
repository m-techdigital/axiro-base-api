# Architecture Canonical

- Transaction là lifecycle owner.
- `TransactionLifecycleCatalog` là owner duy nhất của status label, phase, action và blocking reason.
- `TransactionDetailPresenter` là owner shape detail cho Admin/Customer.
- Document chỉ là hồ sơ giao dịch.
- Không port RBAC, company/project/team, Accounting, SLA/fraud/BI engine.
