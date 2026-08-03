# Module map
Kept: internal Auth/User, Customer auth/profile, Products, Offer modes, Availability/Holds, Transactions, Payments, Wallets, Withdrawals/Payouts, Marketplace Documents, Notifications, Audit logs, Dashboard, shared request/response/list-query helpers.

Removed/excluded: parent RBAC, roles/permissions UI, companies, departments, projects, HR/payroll, CRM reservations/opportunities, accounting, BI reports, generic workflow automation and parent organization hierarchy.

Transaction remains the lifecycle owner. Generated documents are transaction records/supporting artifacts, not a standalone Contract business module.
