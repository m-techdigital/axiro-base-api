# AXIRO Mini - UI, Action, Filter and Formatting Sync

## Mục tiêu

Đồng bộ tiếp AXIRO Mini với AXIRO cha tại các lớp còn thiếu: CSS tokens, responsive primitives, list/filter ownership, action ownership, query filtering API và chuẩn format code.

## Admin foundation

Các owner mới hoặc được chuẩn hóa:

- `BaseFilter`: một owner cho filter theo schema field, tìm kiếm, reset và responsive layout.
- `BaseActionGroup`: một owner cho cụm action trong table/page.
- `BaseConfirmActionButton`, `BaseDeleteButton`: xác nhận thao tác nguy hiểm thống nhất.
- `BaseFormPage`: page form dùng chung header và surface.
- `BaseListView`: tách filter surface và data surface thống nhất.
- `BaseTable`: luôn sở hữu horizontal scroll và empty state.

Bốn danh sách lõi `customers`, `products`, `contracts`, `transactions` đã bỏ `Input.Search`, `Card`, `Popconfirm` tự dựng và chuyển sang base owner.

## CSS foundation

Đã bổ sung cùng convention AXIRO cha:

- `styles/tokens/breakpoints.css`
- `styles/tokens/layout.css`
- `styles/primitives/responsive.css`

`admin-foundation.css` bổ sung contract cho filter grid, action group, list surface, table density và mobile stacking.

## API foundation

Đã bổ sung:

- `ListQueryRequest`: validate keyword, status, listing type, pagination và sorting.
- `AppliesListQuery`: một pipeline dùng chung cho keyword, exact filter và sort allowlist.

Các controller lõi Product, Customer, Contract, Transaction và Listing đã dùng chung pipeline này.

## Formatting

Admin dùng chuẩn giống AXIRO cha:

- 4 spaces
- single quote
- no semicolon

Đã thêm `.prettierrc`, `npm run format` và `npm run format:check`. Các file thay đổi trong phiên đã được format theo chuẩn này. API file thay đổi được format theo Laravel/Pint style và đã chạy PHP syntax check.

## Gate

- `npm run check:parent-ui-parity`
- `php artisan test --filter=ParentListFoundationContractTest`

Gate bảo vệ base filter/action/list ownership và API list-query ownership.

## Giới hạn chủ động

Không copy các base component chuyên domain từ AXIRO cha khi Mini chưa sở hữu domain tương ứng. Việc đồng bộ ưu tiên cùng contract và owner, không copy toàn bộ hệ thống cha.
