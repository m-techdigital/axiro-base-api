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
