# API contracts
Canonical API prefix is `/api/v1`.

- Internal admin auth: login, refresh, current user and logout.
- Customer auth/profile/security: customer login, register, refresh, profile, avatar, wallet and payout flows.
- Marketplace: products, offer modes, availability, transactions, payments, disputes, documents, notifications and Escrow Box.
- Generated documents are transaction artifacts. Do not reintroduce a standalone Contract CRUD API.
