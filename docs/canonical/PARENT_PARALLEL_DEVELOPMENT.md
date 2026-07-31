# AXIRO Mini API — phát triển song song với AXIRO cha

Mini API dùng cùng convention nền tảng với AXIRO cha, nhưng chỉ giữ bounded context Marketplace.

## Cấu trúc canonical

- `app/Contracts`: contract dùng giữa controller/service/infrastructure.
- `app/Http/Requests`: validation owner.
- `app/Http/Resources`: customer/admin response projection.
- `app/Http/Responses`: response envelope owner.
- `app/Repositories`: query abstraction dùng khi query được tái sử dụng; không tạo repository hình thức.
- `app/Services`: application/domain services.
- `app/Support`: pure helpers và contract metadata.
- `routes/api`: route tách theo `auth`, `public`, `customer`, `admin`.
- `tests/Support`: fixture helpers dùng lại giữa feature tests.

## Nguyên tắc đồng bộ

1. Giữ cùng tên và vị trí foundation với AXIRO cha khi Mini có cùng nhu cầu.
2. Không port RBAC, company/project scope, Accounting, HR, Reports nếu Mini không sở hữu nghiệp vụ đó.
3. Controller không giữ validation, pagination envelope hoặc DTO lặp lại.
4. Fixture test phải thỏa migration/model thật; không giả định factory tồn tại.
5. Route mới phải đặt đúng file theo audience.

Chạy `php artisan test --filter=ParentParallelStructureTest` trước khi merge foundation changes.
