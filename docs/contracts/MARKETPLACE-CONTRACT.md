# Marketplace contract ownership

`resources/contracts/marketplace-contract.json` is the canonical integration boundary shared by AXIRO Mini API, AXIRO Mini Admin and MBN React.

Rules:

- Backend owns lifecycle, money, permission decisions, status enums and response contracts.
- Admin and MBN may only call endpoints declared for their audience.
- New capabilities are added in AXIRO parent first, then deliberately ported into Mini API, then exposed to Admin/MBN.
- A breaking change increments the major `contract_version`.
- Frontend repositories keep a checked-in snapshot and fail `check:api-contract` when they drift.
- Customer APIs never expose internal audit payloads; Admin may access audit endpoints.
