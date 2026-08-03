# Known limitations
Single administrator with many marketplace customers. The API intentionally excludes RBAC graphs, company/department/project scope, HR, accounting, BI reports, CRM reservations and generic workflow automation.

Current bounded scope includes internal admin auth, customer auth/profile, products, offer modes, availability/hold history, transactions, payments, wallets, withdrawals, payouts, marketplace documents, notifications and audit logs.

Approval, publication and availability are product/transaction lifecycle fields only. Do not add a parent approval-center, accounting reconciliation module or organization hierarchy unless a real MBN consumer is approved.
