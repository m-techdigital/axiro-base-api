# AXIRO Mini API Canonical Architecture

AXIRO cha là chuẩn kỹ thuật. Mini chỉ rút gọn miền nghiệp vụ, không tạo response, validation, pagination hoặc controller architecture riêng.

- Response owner: `App\Http\Responses\ApiResponse`.
- Pagination owner: `App\Support\Http\PaginationMeta`.
- Validation owner: các `ApiFormRequest` dưới `app/Http/Requests`.
- Output DTO owner: `app/Http/Resources`.
- Controller mỏng, lifecycle nằm trong service.
- Không kéo RBAC/company/project/accounting của AXIRO cha vào Mini.
- Helper cũ `success_response`/`error_response` chỉ là compatibility facade và phải delegate tới `ApiResponse`.

- `TRANSACTION_DOCUMENT_DISPUTE_CLOSURE_20260802.md`: chuẩn hóa key hồ sơ giao dịch và outcome tranh chấp cuối.
- `TRANSACTION_OPTIONS_DISPUTE_OUTCOME_E2E_20260802.md`

- `MARKETPLACE_OPTIONS_RENTAL_E2E_20260802.md`: options cache/version, dispute timeline và rental E2E.
- `MARKETPLACE_OPTIONS_RENTAL_DEDUCTION_CLOSURE_20260802.md`
- `ADMIN_NOTIFICATION_RENTAL_SETTLEMENT_CLOSURE_20260803.md`: admin notification center, rental operation queues và settlement audit/export.
- `ADMIN_NOTIFICATION_SETTLEMENT_FILTER_BUNDLE_CLOSURE_20260803.md`: notification detail drawer, settlement filters/export và route bundle splitting.
- `OPERATIONS_PRESETS_EXPORT_QUEUE_20260803.md`: saved filter presets, unread notification counter và queued rental settlement export.
- `ADMIN_BASE_CRUD_ACTION_ALIGNMENT_20260803.md`: chuẩn hóa Admin CRUD form/detail action theo base owner và parent pattern.
- `ARCHITECTURE-CANONICAL.md`: transaction là lifecycle owner, document chỉ là hồ sơ giao dịch.
- `OPERATOR-GUIDE.md`: hướng dẫn admin/customer dùng command center, next action và checklist.
- `NEXT-BACKLOG.md`: backlog tiếp theo để khép vòng vận hành mà không phình module.
- `NOTIFICATION-PAYOUT-JOURNEY-CLOSURE-20260803.md`
- `OPERATIONAL-AUDIT-TODAY-QUEUE-CLOSURE-20260803.md`
- [Large file ownership closure](./LARGE_FILE_OWNERSHIP_CLOSURE_20260803.md)
- [Large file domain ownership closure](./LARGE_FILE_DOMAIN_OWNERSHIP_20260803.md)

- [Lifecycle, relation, UI and lightweight E2E closure](./LIFECYCLE_RELATION_UI_E2E_CLOSURE_20260803.md)

## Recovery baseline

- [Recovery audit 2026-08-04](../release/RECOVERY-AUDIT-20260804.md)
- `docs/release/recovery-baseline.json` là manifest carry-forward bắt buộc; ZIP mới không tự động được xem là mới hơn baseline đã finalize.

## Owner split follow-up — 2026-08-05

- Admin `BaseForm` delegates AntD field controls to `BaseFormControl`; `BaseFilter` delegates filter controls to `BaseFilterControl`; `BaseTable` delegates empty presentation to `BaseTableEmptyState`. Public props and marketplace behavior remain unchanged.
- Reconciliation export progress is a demand-loaded owner and keeps a visible, accessible loading fallback instead of rendering an empty gap while the chunk loads.
- MBN global `app.css` no longer imports modal sub-owners twice. Account, purchase-detail and content-route styles remain owned by their route shells/pages. `check:global-style-boundary` prevents route-only styles from leaking back into the global manifest.
- No API schema, route, transaction, payout, document, customer-isolation or marketplace contract changed in this follow-up.
- All prior bundle sizes, browser screenshots, transactional E2E and release evidence are historical after this source change. Fresh build, visual regression and `release:all` are required from clean committed/pushed Git HEADs.

- `MARKETPLACE_ESCROW_DIGITAL_ASSET_20260805.md`: luồng giao dịch trung gian Mini-bounded cho tài khoản và vật phẩm trong game, snapshot bàn giao, inspection window và ranh giới dữ liệu nhạy cảm.

- [Escrow Box canonical](./ESCROW_BOX_CANONICAL_20260805.md) — private one-time invite, versioned terms, fee snapshot, Admin handover and optimized private media.
