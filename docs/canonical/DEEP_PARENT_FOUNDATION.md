# AXIRO Mini Deep Foundation Sync

## Mục tiêu

Tiếp tục đồng bộ AXIRO Mini với foundation của AXIRO cha nhưng giữ đúng ranh giới nghiệp vụ hiện tại: một admin vận hành, nhiều customer, không RBAC/company/project/accounting/HR.

## Foundation được áp dụng thêm

### Admin

- Query state tập trung qua `useBaseFilters`, đồng bộ URL, pagination và reset.
- List runtime qua `useList`, có abort request, stale-response guard, error/loading/meta thống nhất.
- Action runtime qua `useTableAction` và `useConfirmActionRunner`.
- API adapter tập trung cho data, pagination và error envelope.
- Axios tự gắn request ID/correlation ID, refresh token chỉ chạy một promise.
- Utility owner cho params, route building, formatter và notification.

### API

- Customer authentication dùng FormRequest cho register/login/2FA/password/email flows.
- Password policy tập trung trong `SecurityPasswordRules` và `config/security.php`.
- API exception envelope dùng `ApiExceptionResponse` thay vì dựng JSON trong bootstrap.
- Request ID và correlation ID được quản lý bởi middleware/context dùng chung.
- Response metadata luôn mang request ID, correlation ID và marketplace contract.

## Những phần chủ động không port

- RBAC và permission graph nhiều vai trò.
- Company/project/department/team scope.
- Accounting, payroll, reports và workflow engine của Mylands.
- Multi-tenant abstractions không có trong Mini.
- Generic repository/service chỉ để tăng số lớp.

## Nguyên tắc phát triển song song

1. Foundation tương đồng phải giữ cùng tên và contract với AXIRO cha.
2. Mini chỉ port foundation khi có ít nhất hai nơi sử dụng hoặc có lợi ích rõ về bảo mật, test, consistency.
3. Không port domain owner mà Mini chưa sở hữu.
4. Filter/sort/pagination phải có allowlist và query-state owner.
5. Action mutation phải đi qua action runner, loading/error/confirmation thống nhất.
6. API error phải có mã lỗi, field errors, request ID và correlation ID.
7. Test fixture phải phản ánh schema thật; không giả định factory không tồn tại.

## Quality gates

- Admin: `npm run check:parent-deep-foundation`.
- API: `php artisan test --filter=ParentDeepFoundationContractTest`.
- PHP syntax: toàn bộ `app`, `tests`, `routes`, `config`, `bootstrap`.
