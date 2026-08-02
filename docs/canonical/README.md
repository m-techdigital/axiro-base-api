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
