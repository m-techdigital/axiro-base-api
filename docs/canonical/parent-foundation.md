# Parent-aligned API Foundation

## Canonical owners
- `ApiResponse`: success/error envelope and contract headers.
- `PaginationMeta`: pagination metadata.
- `ApiFormRequest`: Vietnamese 422 envelope.
- `ApiResource`: output contract base.
- Domain services: lifecycle and integrity changes.

## Compatibility boundary
Legacy helper functions remain as facades only. They delegate to the canonical response owner and must not contain a second response implementation.
